<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactFormMail;

class ContactController extends Controller
{
    public function submit(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email',
            'message' => 'required|string',
        ]);

        Mail::to('support@cleanmydata.com')
            ->send(new ContactFormMail(
                $request->name,
                $request->email,
                $request->message
        ));

        return redirect()->route('contact')->with('success', 'Your message has been sent successfully!');
    }
}
