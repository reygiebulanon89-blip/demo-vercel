# WasteLess REST API Documentation

## Setup Instructions

### 1. Database Setup
- Open phpMyAdmin (http://localhost/phpmyadmin)
- Create a new database named `wasteless`
- Import the `database.sql` file to create all tables

Or run this in MySQL terminal:
```sql
mysql -u root < api/database.sql
```

### 2. Configuration
- Edit `api/config/Database.php` if your MySQL credentials are different
- Edit `api/utils/JWT.php` and change the `secret_key` to a secure random string

### 3. Access the API
- Base URL: `http://localhost/wasteless/api/`
- All responses are in JSON format

---

## Authentication

### Register
**POST** `/auth/register`

Request body:
```json
{
  "username": "john_doe",
  "email": "john@example.com",
  "password": "securepass123",
  "bio": "I love sustainability",
  "profile_pic": "url_to_image"
}
```

Response:
```json
{
  "status": "success",
  "message": "User registered successfully",
  "data": {
    "user": { ... },
    "token": "jwt_token_here"
  }
}
```

### Login
**POST** `/auth/login`

Request body:
```json
{
  "email": "john@example.com",
  "password": "securepass123"
}
```

---

## API Routes

### Users

#### Get All Users
**GET** `/users?limit=50`

#### Get User Profile
**GET** `/users/:id`
or
**GET** `/users/profile` (authenticated - returns current user)

#### Update Profile
**PUT** `/users/profile`

Request body:
```json
{
  "username": "new_username",
  "bio": "Updated bio",
  "profile_pic": "new_image_url"
}
```

#### Follow User
**POST** `/users/follow/:id`

#### Unfollow User
**DELETE** `/users/unfollow/:id`

#### Get Followers
**GET** `/users/followers/:user_id`

#### Get Following
**GET** `/users/following/:user_id`

---

### Feed (Posts)

#### Get Feed
**GET** `/feed?limit=20&offset=0`

#### Create Post
**POST** `/posts`

Request body:
```json
{
  "content": "Just saved 5kg of waste today! 🌱",
  "image": "image_url"
}
```

#### Get Post
**GET** `/posts/:id`

#### Update Post
**PUT** `/posts/:id`

Request body:
```json
{
  "content": "Updated content"
}
```

#### Delete Post
**DELETE** `/posts/:id`

#### Get User Posts
**GET** `/users/:user_id/posts?limit=20&offset=0`

#### Like Post
**POST** `/posts/:id/like`

#### Unlike Post
**DELETE** `/posts/:id/unlike`

#### Get Post Comments
**GET** `/posts/:id/comments?limit=20&offset=0`

#### Create Comment
**POST** `/posts/:id/comments`

Request body:
```json
{
  "comment": "Great effort!"
}
```

#### Delete Comment
**DELETE** `/comments/:id`

#### Update Comment
**PUT** `/comments/:id`

Request body:
```json
{
  "comment": "Updated comment"
}
```

---

### Challenges

#### Get All Challenges
**GET** `/challenges?limit=20&offset=0`

#### Create Challenge
**POST** `/challenges`

Request body:
```json
{
  "title": "Zero Waste Week",
  "description": "Reduce waste to zero for one week",
  "target": 0,
  "target_unit": "kg",
  "start_date": "2026-04-22",
  "end_date": "2026-04-29"
}
```

#### Get Challenge
**GET** `/challenges/:id`

#### Join Challenge
**POST** `/challenges/:id/join`

#### Leave Challenge
**DELETE** `/challenges/:id/leave`

#### Update Progress
**PUT** `/challenges/:id/progress`

Request body:
```json
{
  "progress": 25.5
}
```

#### Get User Challenges
**GET** `/user/challenges?limit=20&offset=0`

---

### Tracking & Analytics

#### Log Waste
**POST** `/tracking/log`

Request body:
```json
{
  "waste_type": "plastic",
  "quantity": 5,
  "unit": "kg",
  "co2_saved": 10
}
```

#### Get User Tracking Logs
**GET** `/tracking/logs?limit=50&offset=0`

#### Get User Stats
**GET** `/tracking/stats`

Response:
```json
{
  "status": "success",
  "data": {
    "total_logs": 25,
    "total_waste": 125.5,
    "total_co2_saved": 251,
    "avg_waste": 5.02
  }
}
```

#### Get Waste Breakdown by Type
**GET** `/tracking/breakdown`

#### Get Global Stats
**GET** `/tracking/global-stats`

#### Delete Tracking Log
**DELETE** `/tracking/logs/:id`

---

## Authentication Header

For authenticated routes, include the JWT token in the Authorization header:

```
Authorization: Bearer your_jwt_token_here
```

---

## Error Responses

### 400 Bad Request
```json
{
  "status": "error",
  "message": "Bad request",
  "data": null
}
```

### 401 Unauthorized
```json
{
  "status": "error",
  "message": "No token provided",
  "data": null
}
```

### 404 Not Found
```json
{
  "status": "error",
  "message": "Route not found",
  "data": null
}
```

### 500 Server Error
```json
{
  "status": "error",
  "message": "Server error",
  "data": null
}
```

---

## Features

✅ User Authentication (JWT)
✅ User Profiles & Following
✅ Social Feed (Posts, Likes, Comments)
✅ Sustainability Challenges
✅ Waste Tracking
✅ Analytics & Statistics
✅ CORS Enabled

---

## Technologies Used

- PHP 7.4+
- MySQL
- JWT Authentication
- PDO (PHP Data Objects)
