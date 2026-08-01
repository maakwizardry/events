<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '0.7.0',
    title: 'Events API - Luma-Inspired Event Management',
    description: 'Open-source headless Laravel event management API from Maak. Features organizations, events, ticket types, registrations, QR check-ins, and waitlist management.',
    contact: new OA\Contact(
        name: 'Maak',
        url: 'https://github.com/maakwizardry/events'
    )
)]
#[OA\Server(
    url: 'http://events.test/api/v1',
    description: 'Local Development Server'
)]
#[OA\Server(
    url: 'http://localhost:8000/api/v1',
    description: 'PHP Built-in Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Use the token from POST /auth/login or POST /auth/register'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'User authentication and account management'
)]
#[OA\Tag(
    name: 'Organizations',
    description: 'Organization and team member management'
)]
#[OA\Tag(
    name: 'Events',
    description: 'Event creation, management, and discovery'
)]
#[OA\Tag(
    name: 'Public Events',
    description: 'Public event discovery and information (no authentication required)'
)]
#[OA\Tag(
    name: 'Ticket Types',
    description: 'Ticket type management for events'
)]
#[OA\Tag(
    name: 'Registrations',
    description: 'Event registration management'
)]
#[OA\Tag(
    name: 'Check-In',
    description: 'QR code check-in and statistics'
)]
abstract class Controller
{
    //
}
