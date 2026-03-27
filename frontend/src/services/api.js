class ApiError extends Error {
  constructor(status, data, message) {
    super(message);
    this.response = { status, data };
  }
}

const api = {
  baseURL: '',

  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...options.headers,
    };

    const token = localStorage.getItem('token');
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const config = {
      ...options,
      headers,
    };

    if (config.body && typeof config.body === 'object') {
      config.body = JSON.stringify(config.body);
    }

    try {
      const response = await fetch(url, config);
      
      // Attempt to parse JSON response
      let data = null;
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        try {
          data = await response.json();
        } catch (e) {
          // Ignore JSON parse errors for empty responses
        }
      }

      if (!response.ok) {
        console.error('API Error:', response.status, data);
        
        if (response.status === 401) {
          localStorage.removeItem('token');
          localStorage.removeItem('user');
          window.location.href = '/';
        }
        
        throw new ApiError(response.status, data, `API Error: ${response.status}`);
      }

      return { data, status: response.status, headers: response.headers };
    } catch (error) {
      if (error instanceof ApiError) {
        throw error;
      }
      // Network errors or other fetch failures
      console.error('Network Error:', error);
      throw new ApiError(0, null, error.message);
    }
  },

  get(endpoint, options = {}) {
    return this.request(endpoint, { ...options, method: 'GET' });
  },

  post(endpoint, data, options = {}) {
    return this.request(endpoint, { ...options, method: 'POST', body: data });
  },

  put(endpoint, data, options = {}) {
    return this.request(endpoint, { ...options, method: 'PUT', body: data });
  },

  delete(endpoint, options = {}) {
    return this.request(endpoint, { ...options, method: 'DELETE' });
  }
};

export default api;
