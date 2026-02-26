#!/usr/bin/env python3
"""
LLM CLIENT - Shared Module
===========================
Single source of truth for the Gemini LLM client.
Imported by: data_cleaner.py, cross_reference.py, context_validator.py

Provides:
  - GeminiLLM      : REST client for Gemini 2.0 Flash
  - _safe_stderr() : Windows-compatible stderr logger

Methods on GeminiLLM:
  .call(prompt, max_tokens)          → raw text response
  .detect_column_types(profile)      → {col: {is_date, can_be_negative, ...}}
  .format_dates(col, values)         → {original_value: "YYYY-MM-DD"}
  .format_prices(col, values)        → {original_value: float}
"""

import os
import sys
import json
import time
import urllib.request


# ============================================================================
# STDERR HELPER
# ============================================================================

def _safe_stderr(*args, **kwargs):
    """Print to stderr — Windows-compatible (no UnicodeEncodeError)."""
    text = ' '.join(str(a) for a in args)
    try:
        print(text, file=sys.stderr)
    except UnicodeEncodeError:
        safe = text.encode('ascii', errors='replace').decode('ascii')
        print(safe, file=sys.stderr)


# ============================================================================
# GEMINI LLM CLIENT
# ============================================================================

class GeminiLLM:
    """
    REST client for Gemini 2.0 Flash via urllib (no extra dependencies).
    Reads GEMINI_API_KEY from environment.

    Usage:
        llm = GeminiLLM()
        if llm.available:
            response = llm.call("Your prompt here")
    """

    API_URL = (
        "https://generativelanguage.googleapis.com"
        "/v1beta/models/gemini-2.0-flash:generateContent"
    )
    MODEL = "gemini-2.0-flash"

    # Retry config
    MAX_RETRIES  = 3
    RETRY_WAITS  = [30, 60, 90]   # seconds between attempts on 429

    def __init__(self):
        self.available = False
        self._api_key  = None

        api_key = os.environ.get("GEMINI_API_KEY", "").strip()
        if not api_key:
            _safe_stderr("WARNING: GEMINI_API_KEY not set — LLM fallback active")
            return

        # Validate key with a minimal test call
        try:
            self._api_key = api_key
            self._post({"contents": [{"parts": [{"text": "test"}]}],
                        "generationConfig": {"maxOutputTokens": 5}})
            self.available = True
            _safe_stderr(f"OK GeminiLLM initialized ({self.MODEL})")
        except Exception as e:
            _safe_stderr(f"WARNING: GeminiLLM not available: {e}")
            self._api_key = None

    # ── Core HTTP ─────────────────────────────────────────────────────────────

    def _post(self, payload: dict, timeout: int = 60) -> dict:
        """POST payload to Gemini API. Returns parsed JSON response."""
        data = json.dumps(payload).encode()
        req  = urllib.request.Request(
            self.API_URL + "?key=" + self._api_key,
            data=data,
            headers={"Content-Type": "application/json"},
            method="POST",
        )
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return json.loads(r.read())

    def call(self, prompt: str, max_tokens: int = 1000) -> str | None:
        """
        Send a prompt to Gemini and return the text response.
        Returns None if LLM is unavailable or all retries fail.
        """
        if not self.available:
            return None

        payload = {
            "contents": [{"parts": [{"text": prompt}]}],
            "generationConfig": {
                "maxOutputTokens": max_tokens,
                "temperature": 0,
            },
        }

        for attempt in range(self.MAX_RETRIES):
            try:
                resp = self._post(payload)
                return resp["candidates"][0]["content"]["parts"][0]["text"]

            except Exception as e:
                err_str = str(e)
                if "429" in err_str and attempt < self.MAX_RETRIES - 1:
                    wait = self.RETRY_WAITS[attempt]
                    _safe_stderr(f"WARNING: Gemini rate limit (429), retrying in {wait}s…")
                    time.sleep(wait)
                else:
                    _safe_stderr(f"WARNING: Gemini API error (attempt {attempt+1}): {e}")
                    return None

        return None

    # ── Structured helpers ────────────────────────────────────────────────────

    def detect_column_types(self, profile: dict) -> dict | None:
        """
        Ask Gemini to classify every column.

        Returns a dict keyed by column name:
          {
            "col_name": {
              "is_date": bool,
              "can_be_negative": bool,
              "negative_action": "abs" | "null" | null,
              "reason": str
            }
          }
        Returns None on failure (caller should use heuristic fallback).
        """
        prompt = f"""You are a data-type detection expert.

Dataset profile (JSON):
{json.dumps(profile, indent=2)}

For EVERY column listed, decide:
1. is_date        : true if the column stores date/datetime values
2. can_be_negative: true if negative numbers are semantically valid
3. negative_action: if can_be_negative=false, choose "abs" (sign error likely)
                    or "null" (value is simply impossible)

Rules:
- Numeric columns that are IDs, codes, or counts are NOT dates.
- Date columns must have string values that look like dates.
- "salary", "price", "revenue" → can_be_negative=false, negative_action="abs"
- "age", "count", "score", "distance" → can_be_negative=false, negative_action="null"
- "temperature", "balance", "profit", "delta" → can_be_negative=true

Respond ONLY with valid JSON (no markdown):
{{
  "column_name": {{
    "is_date": false,
    "can_be_negative": true,
    "negative_action": null,
    "reason": "brief explanation"
  }}
}}"""

        raw = self.call(prompt, max_tokens=2000)
        if not raw:
            return None

        try:
            # Strip possible markdown code fences
            clean = raw.strip().lstrip("```json").lstrip("```").rstrip("```").strip()
            return json.loads(clean)
        except Exception as e:
            _safe_stderr(f"WARNING: detect_column_types parse error: {e}")
            return None

    def format_dates(self, column_name: str, values: list) -> dict | None:
        """
        Ask Gemini to convert a list of raw date strings to YYYY-MM-DD.

        Returns {original_value: "YYYY-MM-DD"} or None on failure.
        Unrecognised values map to null.
        """
        if not values:
            return None

        # Deduplicate to reduce token usage
        unique_vals = list(dict.fromkeys(str(v) for v in values))
        # Cap at 200 unique values per call to stay within token limits
        sample = unique_vals[:200]

        prompt = f"""Convert these raw date values from column "{column_name}" to ISO 8601 (YYYY-MM-DD).

Values to convert:
{json.dumps(sample, indent=2)}

Rules:
- Output ONLY valid YYYY-MM-DD strings or null for unrecognisable values.
- For ambiguous DD/MM vs MM/DD, prefer DD/MM unless the day > 12.
- Do not invent dates — use null when genuinely uncertain.

Respond ONLY with valid JSON (no markdown):
{{
  "original_value": "YYYY-MM-DD",
  "another_value": null
}}"""

        raw = self.call(prompt, max_tokens=3000)
        if not raw:
            return None

        try:
            clean = raw.strip().lstrip("```json").lstrip("```").rstrip("```").strip()
            mapping = json.loads(clean)
            # Validate: values must be YYYY-MM-DD or null
            import re
            date_re = re.compile(r"^\d{4}-\d{2}-\d{2}$")
            return {
                k: v if (v and date_re.match(str(v))) else None
                for k, v in mapping.items()
            }
        except Exception as e:
            _safe_stderr(f"WARNING: format_dates parse error for '{column_name}': {e}")
            return None

    def format_prices(self, column_name: str, values: list) -> dict | None:
        """
        Ask Gemini to parse raw monetary strings to float.

        Returns {original_value: float} or None on failure.
        Unrecognised values map to null.
        """
        if not values:
            return None

        unique_vals = list(dict.fromkeys(str(v) for v in values))
        sample = unique_vals[:200]

        prompt = f"""Parse these raw monetary/price values from column "{column_name}" to plain float numbers.

Values to parse:
{json.dumps(sample, indent=2)}

Rules:
- Strip currency symbols (€, $, £, ¥, USD, EUR, etc.).
- Handle thousand separators (1,000.00 → 1000.0 ; 1.000,00 → 1000.0).
- "free" or "gratuit" → 0.0
- Return null for values that cannot be parsed as a number.

Respond ONLY with valid JSON (no markdown):
{{
  "original_value": 1234.56,
  "another_value": null
}}"""

        raw = self.call(prompt, max_tokens=2000)
        if not raw:
            return None

        try:
            clean = raw.strip().lstrip("```json").lstrip("```").rstrip("```").strip()
            mapping = json.loads(clean)
            # Coerce to float or None
            result = {}
            for k, v in mapping.items():
                try:
                    result[k] = float(v) if v is not None else None
                except (TypeError, ValueError):
                    result[k] = None
            return result
        except Exception as e:
            _safe_stderr(f"WARNING: format_prices parse error for '{column_name}': {e}")
            return None