import api from './api';

class AuthService {
  async getCsrfCookie() {
    return await api.get('/sanctum/csrf-cookie');
  }

  async handleJiraCallback(code, state) {
    const response = await api.post('/api/auth/jira/callback', { code, state });
    return response.data;
  }
}

export default new AuthService();