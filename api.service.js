/**
 * WasteLess API Service Module
 * Handles all API calls to the backend
 */

const API_BASE_URL = 'http://localhost/wasteless/api';
const NOTIFICATIONS_STREAM_URL = 'http://localhost/wasteless/api/notifications-stream.php';

class ApiService {
  constructor() {
    this.token = localStorage.getItem('auth_token');
  }

  /**
   * Get headers with authorization token
   */
  getHeaders(contentType = 'application/json') {
    const headers = {
      'Content-Type': contentType
    };
    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }
    return headers;
  }

  /**
   * Handle API response
   */
  async handleResponse(response) {
    const data = await response.json();

    if (!response.ok) {
      // Handle 401 Unauthorized - redirect to login
      if (response.status === 401) {
        this.logout();
        window.location.href = 'index.html';
      }
      throw new Error(data.message || `Error: ${response.status}`);
    }

    return data;
  }

  /**
   * Set authentication token
   */
  setToken(token) {
    this.token = token;
    localStorage.setItem('auth_token', token);
  }

  /**
   * Clear authentication
   */
  logout() {
    this.token = null;
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_data');
  }

  /**
   * Get stored user data
   */
  getUserData() {
    const data = localStorage.getItem('user_data');
    return data ? JSON.parse(data) : null;
  }

  /**
   * Set user data
   */
  setUserData(userData) {
    localStorage.setItem('user_data', JSON.stringify(userData));
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated() {
    return !!this.token;
  }

  // ===== AUTH ENDPOINTS =====

  /**
   * Register new user
   */
  async register(username, email, password, bio = '') {
    const response = await fetch(`${API_BASE_URL}/auth/register`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify({ username, email, password, bio })
    });
    return this.handleResponse(response);
  }

  /**
   * Login user
   */
  async login(email, password) {
    const response = await fetch(`${API_BASE_URL}/auth/login`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify({ email, password })
    });
    return this.handleResponse(response);
  }

  // ===== USER ENDPOINTS =====

  /**
   * Get current user profile
   */
  async getProfile() {
    const response = await fetch(`${API_BASE_URL}/users/profile`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Get user profile by ID
   */
  async getUserById(userId) {
    const response = await fetch(`${API_BASE_URL}/users/${userId}`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Update user profile
   */
  async updateProfile(username, bio, profilePicUrl = null, profilePicFile = null) {
    // If a file is provided, send multipart/form-data (do NOT set Content-Type manually).
    if (profilePicFile) {
      const form = new FormData();
      form.append('username', username ?? '');
      form.append('bio', bio ?? '');
      if (profilePicUrl) form.append('profile_pic', profilePicUrl);
      form.append('profile_pic_file', profilePicFile);

      const headers = {};
      if (this.token) {
        headers['Authorization'] = `Bearer ${this.token}`;
      }

      const response = await fetch(`${API_BASE_URL}/users/profile`, {
        method: 'POST',
        headers,
        body: form
      });
      return this.handleResponse(response);
    }

    // Default JSON update (backwards compatible)
    const body = { username, bio };
    if (profilePicUrl) body.profile_pic = profilePicUrl;

    const response = await fetch(`${API_BASE_URL}/users/profile`, {
      method: 'PUT',
      headers: this.getHeaders(),
      body: JSON.stringify(body)
    });
    return this.handleResponse(response);
  }

  /**
   * Get all users
   */
  async getAllUsers(limit = 50) {
    const response = await fetch(`${API_BASE_URL}/users?limit=${limit}`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Follow a user
   */
  async followUser(userId) {
    const response = await fetch(`${API_BASE_URL}/users/follow/${userId}`, {
      method: 'POST',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Unfollow a user
   */
  async unfollowUser(userId) {
    const response = await fetch(`${API_BASE_URL}/users/unfollow/${userId}`, {
      method: 'DELETE',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  // ===== TRACKING ENDPOINTS =====

  /**
   * Log waste entry
   */
  async logWaste(wasteType, quantity, unit = 'kg', date = null) {
    const body = { waste_type: wasteType, quantity, unit };
    if (date) body.date = date;
    const response = await fetch(`${API_BASE_URL}/tracking/log`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(body)
    });
    return this.handleResponse(response);
  }

  /**
   * Get user's tracking logs
   */
  async getTrackingLogs(limit = 50, offset = 0) {
    const response = await fetch(`${API_BASE_URL}/tracking/logs?limit=${limit}&offset=${offset}`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Get user stats
   */
  async getTrackingStats() {
    const response = await fetch(`${API_BASE_URL}/tracking/stats`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Get waste breakdown by type
   */
  async getWasteBreakdown() {
    const response = await fetch(`${API_BASE_URL}/tracking/breakdown`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Get global stats
   */
  async getGlobalStats() {
    const response = await fetch(`${API_BASE_URL}/tracking/global-stats`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Delete tracking log
   */
  async deleteLog(logId) {
    const response = await fetch(`${API_BASE_URL}/tracking/logs/${logId}`, {
      method: 'DELETE',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  // ===== FEED ENDPOINTS =====

  /**
   * Get feed
   */
  async getFeed(limit = 20, offset = 0) {
    const response = await fetch(`${API_BASE_URL}/feed?limit=${limit}&offset=${offset}`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Create post
   */
  async createPost(content, image = null) {
    const body = { content };
    if (image) body.image = image;

    const response = await fetch(`${API_BASE_URL}/posts`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(body)
    });
    return this.handleResponse(response);
  }

  /**
   * Get post by ID
   */
  async getPost(postId) {
    const response = await fetch(`${API_BASE_URL}/posts/${postId}`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Update post
   */
  async updatePost(postId, content) {
    const response = await fetch(`${API_BASE_URL}/posts/${postId}`, {
      method: 'PUT',
      headers: this.getHeaders(),
      body: JSON.stringify({ content })
    });
    return this.handleResponse(response);
  }

  /**
   * Delete post
   */
  async deletePost(postId) {
    const response = await fetch(`${API_BASE_URL}/posts/${postId}`, {
      method: 'DELETE',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Get user posts
   */
  async getUserPosts(userId, limit = 20, offset = 0) {
    const response = await fetch(`${API_BASE_URL}/users/${userId}/posts?limit=${limit}&offset=${offset}`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Like post
   */
  async likePost(postId) {
    const response = await fetch(`${API_BASE_URL}/posts/${postId}/like`, {
      method: 'POST',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Unlike post
   */
  async unlikePost(postId) {
    const response = await fetch(`${API_BASE_URL}/posts/${postId}/unlike`, {
      method: 'DELETE',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Get post comments
   */
  async getComments(postId, limit = 20, offset = 0) {
    const response = await fetch(`${API_BASE_URL}/posts/${postId}/comments?limit=${limit}&offset=${offset}`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Create comment
   */
  async createComment(postId, comment, parentCommentId = null) {
    const body = { comment };
    if (parentCommentId) {
      body.parent_comment_id = parentCommentId;
    }

    const response = await fetch(`${API_BASE_URL}/posts/${postId}/comments`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(body)
    });
    return this.handleResponse(response);
  }

  /**
   * Delete comment
   */
  async deleteComment(commentId) {
    const response = await fetch(`${API_BASE_URL}/comments/${commentId}`, {
      method: 'DELETE',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Update comment
   */
  async updateComment(commentId, comment) {
    const response = await fetch(`${API_BASE_URL}/comments/${commentId}`, {
      method: 'PUT',
      headers: this.getHeaders(),
      body: JSON.stringify({ comment })
    });
    return this.handleResponse(response);
  }

  // ===== NOTIFICATION ENDPOINTS =====

  async getNotifications(limit = 30, afterId = 0) {
    const response = await fetch(`${API_BASE_URL}/notifications?limit=${limit}&after_id=${afterId}`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  async markNotificationsRead() {
    const response = await fetch(`${API_BASE_URL}/notifications/read`, {
      method: 'POST',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  async deleteNotification(notificationId) {
    const response = await fetch(`${API_BASE_URL}/notifications/${notificationId}`, {
      method: 'DELETE',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  getNotificationsStreamUrl(lastId = 0) {
    if (!this.token) return null;
    return `${NOTIFICATIONS_STREAM_URL}?token=${encodeURIComponent(this.token)}&last_id=${encodeURIComponent(String(lastId || 0))}`;
  }

  // ===== CHALLENGE ENDPOINTS =====

  /**
   * Get all challenges
   */
  async getChallenges(limit = 20, offset = 0) {
    const response = await fetch(`${API_BASE_URL}/challenges?limit=${limit}&offset=${offset}`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Get challenge by ID
   */
  async getChallenge(challengeId) {
    const response = await fetch(`${API_BASE_URL}/challenges/${challengeId}`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Create challenge
   */
  async createChallenge(title, description, target = 100, targetUnit = 'kg', startDate = null, endDate = null) {
    const body = { title, description, target, target_unit: targetUnit };
    if (startDate) body.start_date = startDate;
    if (endDate) body.end_date = endDate;

    const response = await fetch(`${API_BASE_URL}/challenges`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(body)
    });
    return this.handleResponse(response);
  }

  /**
   * Join challenge
   */
  async joinChallenge(challengeId) {
    const response = await fetch(`${API_BASE_URL}/challenges/${challengeId}/join`, {
      method: 'POST',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Leave challenge
   */
  async leaveChallenge(challengeId) {
    const response = await fetch(`${API_BASE_URL}/challenges/${challengeId}/leave`, {
      method: 'DELETE',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }

  /**
   * Update challenge progress
   */
  async updateChallengeProgress(challengeId, progress) {
    const response = await fetch(`${API_BASE_URL}/challenges/${challengeId}/progress`, {
      method: 'PUT',
      headers: this.getHeaders(),
      body: JSON.stringify({ progress })
    });
    return this.handleResponse(response);
  }

  /**
   * Get user challenges
   */
  async getUserChallenges() {
    const response = await fetch(`${API_BASE_URL}/user/challenges`, {
      method: 'GET',
      headers: this.getHeaders()
    });
    return this.handleResponse(response);
  }
}

// Create global instance
const api = new ApiService();
