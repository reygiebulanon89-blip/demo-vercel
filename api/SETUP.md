# WasteLess Backend - Quick Setup Guide

## ✅ Backend is Ready!

Your complete PHP REST API backend for WasteLess has been created with all necessary features.

---

## 📁 Project Structure

```
api/
├── config/
│   ├── Database.php        # Database connection
│   └── Config.php          # Configuration constants
├── controllers/
│   ├── AuthController.php
│   ├── UserController.php
│   ├── FeedController.php
│   ├── ChallengeController.php
│   └── TrackController.php
├── models/
│   ├── User.php
│   ├── Post.php
│   ├── Comment.php
│   ├── Challenge.php
│   └── Track.php
├── middleware/
│   └── AuthMiddleware.php
├── utils/
│   ├── Response.php        # JSON response helper
│   ├── JWT.php             # JWT authentication
│   └── Helper.php          # Utility functions
├── index.php               # Main router/entry point
├── database.sql            # Database schema
├── .htaccess               # URL rewriting
├── README.md               # API documentation
└── WasteLess_API.postman_collection.json  # Postman collection
```

---

## 🚀 Setup Steps

### Step 1: Create Database
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Create new database: `wasteless`
3. Import `api/database.sql` file OR
   - Execute: `mysql -u root < api/database.sql`

### Step 2: Verify Configuration
Edit these files if needed:
- `api/config/Database.php` - Update MySQL credentials if different
- `api/utils/JWT.php` - Change the secret key to something secure
- `api/config/Config.php` - Adjust settings as needed

### Step 3: Test the API
Access: `http://localhost/wasteless/api/`

---

## 🔐 API Endpoints

### Authentication
```
POST   /auth/register
POST   /auth/login
```

### Users
```
GET    /users
GET    /users/:id
GET    /users/profile
PUT    /users/profile
POST   /users/follow/:id
DELETE /users/unfollow/:id
GET    /users/followers/:id
GET    /users/following/:id
```

### Feed (Posts)
```
GET    /feed
POST   /posts
GET    /posts/:id
PUT    /posts/:id
DELETE /posts/:id
GET    /users/:id/posts
POST   /posts/:id/like
DELETE /posts/:id/unlike
GET    /posts/:id/comments
POST   /posts/:id/comments
DELETE /comments/:id
PUT    /comments/:id
```

### Challenges
```
GET    /challenges
POST   /challenges
GET    /challenges/:id
POST   /challenges/:id/join
DELETE /challenges/:id/leave
PUT    /challenges/:id/progress
GET    /user/challenges
```

### Tracking & Analytics
```
POST   /tracking/log
GET    /tracking/logs
GET    /tracking/stats
GET    /tracking/breakdown
GET    /tracking/global-stats
DELETE /tracking/logs/:id
```

---

## 🧪 Testing with Postman

1. Download and install Postman
2. Import `WasteLess_API.postman_collection.json`
3. Replace `YOUR_TOKEN_HERE` with actual JWT token from login
4. Start testing!

---

## 🔑 Authentication

1. Register a user: `POST /auth/register`
2. Login to get JWT token: `POST /auth/login`
3. Include token in Authorization header for protected routes:
   ```
   Authorization: Bearer your_jwt_token_here
   ```

---

## 📊 Key Features

✅ **User Authentication** - JWT tokens
✅ **User Profiles** - Follow/Unfollow system
✅ **Social Feed** - Posts, likes, comments
✅ **Challenges** - Create & track challenges
✅ **Waste Tracking** - Log waste with CO2 calculations
✅ **Analytics** - User stats & global statistics
✅ **CORS Enabled** - Works with frontend
✅ **Error Handling** - Proper HTTP status codes
✅ **Pagination** - Limit & offset support

---

## 🛠️ Technologies

- PHP 7.4+
- MySQL 5.7+
- JWT Authentication
- PDO (database abstraction)
- RESTful Architecture

---

## 📝 Important Notes

1. Change JWT secret key before production
2. Use HTTPS in production
3. Implement rate limiting for production
4. Add input validation for file uploads
5. Consider adding admin dashboard
6. Set up proper logging system

---

## 🐛 Troubleshooting

### Database connection error?
- Check MySQL is running
- Verify credentials in `api/config/Database.php`
- Ensure database is created

### 404 errors on API calls?
- Check .htaccess is enabled
- Verify Apache has mod_rewrite enabled
- Ensure URLs follow correct format

### JWT errors?
- Verify token is included in header
- Check token hasn't expired (24 hours)
- Ensure header format is: `Authorization: Bearer token`

---

## 📖 Full Documentation

See `README.md` for complete API documentation with examples.

---

## ✨ Next Steps

1. ✅ Test all endpoints with Postman
2. ✅ Connect frontend to API
3. ✅ Implement file upload for images
4. ✅ Add email verification
5. ✅ Set up admin panel
6. ✅ Deploy to production server

Enjoy building! 🌱
