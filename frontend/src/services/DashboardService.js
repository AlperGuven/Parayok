import api from './api';

class DashboardService {
  async getRooms() {
    const response = await api.get('/api/rooms');
    return response.data;
  }

  async deleteRoom(uuid) {
    const response = await api.post(`/api/rooms/${uuid}/delete`);
    return response.data;
  }

  async updateProfile(displayName) {
    const response = await api.post('/api/user/profile', { display_name: displayName });
    return response.data;
  }

  async logout() {
    const response = await api.post('/api/auth/logout');
    return response.data;
  }
}

export default new DashboardService();