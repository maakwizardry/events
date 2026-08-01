# Events API Documentation

Open-source headless Laravel event management API inspired by Luma, built by Maak.

**Version:** 0.7.0
**Base URL:** `http://your-domain.com/api/v1`

## Features

- 🏢 Multi-tenant organization management with team roles
- 🎫 Event creation and management (public/private)
- 🎟️ Multiple ticket types per event with time-based availability
- 👥 User and guest registrations
- 📱 QR code generation for each registration
- ⏳ Automatic waitlist management
- ✅ QR code check-in system
- 📊 Real-time statistics and analytics

## Authentication

The API uses Laravel Sanctum for authentication. Include the token in the `Authorization` header:

```
Authorization: Bearer {your-token}
```

### Register
```http
POST /auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response:**
```json
{
  "message": "User registered successfully",
  "user": {...},
  "token": "1|abc123..."
}
```

### Login
```http
POST /auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

### Get Current User
```http
GET /auth/me
Authorization: Bearer {token}
```

### Logout
```http
POST /auth/logout
Authorization: Bearer {token}
```

---

## Organizations

### List User's Organizations
```http
GET /organizations
Authorization: Bearer {token}
```

### Create Organization
```http
POST /organizations
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Tech Meetups",
  "slug": "tech-meetups",
  "description": "A community for tech enthusiasts",
  "primary_color": "#3B82F6",
  "secondary_color": "#1E40AF"
}
```

### Get Organization
```http
GET /organizations/{uuid}
Authorization: Bearer {token}
```

### Update Organization
```http
PUT /organizations/{uuid}
Authorization: Bearer {token}
```

### Delete Organization
```http
DELETE /organizations/{uuid}
Authorization: Bearer {token}
```

Only owners can delete organizations.

---

## Team Members

### List Members
```http
GET /organizations/{uuid}/members
Authorization: Bearer {token}
```

### Add Member
```http
POST /organizations/{uuid}/members
Authorization: Bearer {token}
Content-Type: application/json

{
  "email": "member@example.com",
  "role": "admin"
}
```

**Roles:** `owner`, `admin`, `member`

### Update Member Role
```http
PUT /organizations/{uuid}/members/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "role": "member"
}
```

### Remove Member
```http
DELETE /organizations/{uuid}/members/{id}
Authorization: Bearer {token}
```

---

## Events

### List Organization Events
```http
GET /organizations/{uuid}/events
Authorization: Bearer {token}
```

### Create Event
```http
POST /events
Authorization: Bearer {token}
Content-Type: application/json

{
  "organization_id": 1,
  "name": "Laravel Workshop 2026",
  "description": "Learn Laravel from scratch",
  "visibility": "public",
  "location_type": "hybrid",
  "location_city": "San Francisco",
  "location_country": "USA",
  "starts_at": "2026-09-01 10:00:00",
  "ends_at": "2026-09-01 16:00:00",
  "timezone": "America/Los_Angeles",
  "capacity": 50,
  "category": "workshop",
  "status": "published"
}
```

**Visibility:** `public`, `private`
**Location Type:** `physical`, `online`, `hybrid`
**Category:** `conference`, `workshop`, `meetup`, `webinar`, `networking`
**Status:** `draft`, `published`, `cancelled`, `completed`

### Update Event
```http
PUT /events/{uuid}
Authorization: Bearer {token}
```

### Delete Event
```http
DELETE /events/{uuid}
Authorization: Bearer {token}
```

---

## Public Events (No Auth Required)

### List Public Events
```http
GET /public/events
```

**Query Parameters:**
- `upcoming` - Filter upcoming events (default: true)
- `category` - Filter by category
- `city` - Filter by city
- `country` - Filter by country
- `latitude`, `longitude`, `radius` - Proximity search (radius in km)
- `location_type` - Filter by location type
- `search` - Search in name/description
- `tags` - Filter by tags (array)
- `start_date`, `end_date` - Date range
- `sort_by` - Sort field (starts_at, created_at, name)
- `sort_order` - asc or desc
- `per_page` - Results per page (default: 20)

**Example:**
```http
GET /public/events?category=workshop&city=San%20Francisco&upcoming=true
```

### View Public Event
```http
GET /public/events/{uuid}
```

### Check Event Availability
```http
GET /public/events/{uuid}/availability
```

---

## Ticket Types

### List Ticket Types
```http
GET /public/events/{uuid}/ticket-types
```

### Create Ticket Type
```http
POST /events/{uuid}/ticket-types
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "General Admission",
  "description": "Standard entry ticket",
  "price": 0,
  "quantity": 100,
  "sales_start_at": "2026-08-01 00:00:00",
  "sales_end_at": "2026-09-01 00:00:00",
  "order": 1
}
```

### Update Ticket Type
```http
PUT /events/{uuid}/ticket-types/{id}
Authorization: Bearer {token}
```

### Delete Ticket Type
```http
DELETE /events/{uuid}/ticket-types/{id}
Authorization: Bearer {token}
```

Cannot delete ticket types with existing registrations.

---

## Registrations

### Register for Event (Guest)
```http
POST /public/events/{uuid}/register
Content-Type: application/json

{
  "ticket_type_id": 1,
  "guest_name": "Jane Doe",
  "guest_email": "jane@example.com",
  "guest_phone": "+1234567890",
  "quantity": 1,
  "custom_fields": {}
}
```

**Response includes:**
- Registration UUID
- QR code data
- QR code URL (for scanning)
- Status (confirmed or waitlisted)

### Register for Event (Authenticated)
```http
POST /events/{uuid}/register
Authorization: Bearer {token}
Content-Type: application/json

{
  "ticket_type_id": 1,
  "quantity": 1
}
```

### View Registration
```http
GET /public/registrations/{uuid}
```

### List User Registrations
```http
GET /registrations
Authorization: Bearer {token}
```

### Cancel Registration
```http
POST /registrations/{uuid}/cancel
Authorization: Bearer {token}
```

---

## Check-In (Organization Members Only)

### Check In Attendee
```http
POST /events/{uuid}/check-in
Authorization: Bearer {token}
Content-Type: application/json

{
  "qr_code_data": "abc123xyz456",
  "location": "Main Entrance"
}
```

**Validations:**
- QR code must exist
- QR code must belong to this event
- Registration must be confirmed (not waitlisted)
- Prevents duplicate check-ins

**Response:**
```json
{
  "message": "Check-in successful",
  "registration": {...}
}
```

### Get Check-In Statistics
```http
GET /events/{uuid}/check-in/stats
Authorization: Bearer {token}
```

**Returns:**
- Total registrations
- Checked-in count
- Pending check-in count
- Waitlisted count
- Cancelled count
- Check-in rate percentage
- Check-ins by hour (last 24 hours)
- Ticket type breakdown

### List Event Registrations
```http
GET /events/{uuid}/registrations
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` - Filter by status (confirmed, waitlisted, cancelled)
- `checked_in` - Filter by check-in status (true/false)
- `search` - Search by attendee name or email
- `per_page` - Results per page (default: 50)

---

## Response Format

All responses follow a consistent JSON format:

**Success:**
```json
{
  "data": {...},
  "message": "Operation successful"
}
```

**Collection:**
```json
{
  "data": [...],
  "links": {...},
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100
  }
}
```

**Error:**
```json
{
  "message": "Error message",
  "errors": {
    "field": ["Validation error"]
  }
}
```

---

## Status Codes

- `200` - Success
- `201` - Created
- `401` - Unauthenticated
- `403` - Unauthorized (insufficient permissions)
- `404` - Not Found
- `422` - Validation Error

---

## Rate Limiting

- Public endpoints: 60 requests/minute
- Authenticated endpoints: 120 requests/minute
- Check-in endpoints: 200 requests/minute

---

## Testing with cURL

### Register and Login
```bash
# Register
curl -X POST http://events.test/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com","password":"password123","password_confirmation":"password123"}'

# Login
curl -X POST http://events.test/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123"}'
```

### Create Organization
```bash
TOKEN="your-token-here"
curl -X POST http://events.test/api/v1/organizations \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Tech Events","slug":"tech-events"}'
```

### List Public Events
```bash
curl http://events.test/api/v1/public/events?category=workshop&upcoming=true
```

### Register for Event (Guest)
```bash
curl -X POST http://events.test/api/v1/public/events/{event-uuid}/register \
  -H "Content-Type: application/json" \
  -d '{"ticket_type_id":1,"guest_name":"Jane Doe","guest_email":"jane@example.com"}'
```

---

## Postman Collection

A Postman collection is available at `/postman/events-api-collection.json` with pre-configured requests and environment variables.

---

## GitHub Repository

**Repository:** https://github.com/maakwizardry/events
**License:** Open Source
**Built by:** Maak

---

## Support

For issues, feature requests, or contributions, please visit the GitHub repository.
