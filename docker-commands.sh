#!/bin/bash

# Copy environment file
cp .env.example .env

# Build images
docker-compose build

# Start services
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate

# Check status
docker-compose ps

# View logs
docker-compose logs -f

# Stop services
# docker-compose down

# Remove volumes (careful!)
# docker-compose down -v