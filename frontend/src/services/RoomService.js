import api from './api';

class RoomService {
  async getRoom(uuid) {
    const response = await api.get(`/api/rooms/${uuid}`);
    return response.data;
  }

  async createRoom(name, votingSystem = "fibonacci") {
    const response = await api.post("/api/rooms", {
      name,
      voting_system: votingSystem
    });
    return response.data;
  }

  async joinRoom(uuid) {
    const response = await api.post(`/api/rooms/${uuid}/join`);
    return response.data;
  }

  async leaveRoom(uuid) {
    const response = await api.post(`/api/rooms/${uuid}/leave`);
    return response.data;
  }

  async completeRoom(uuid) {
    const response = await api.post(`/api/rooms/${uuid}/complete`);
    return response.data;
  }

  async reopenRoom(uuid) {
    const response = await api.post(`/api/rooms/${uuid}/reopen`);
    return response.data;
  }

  async updateIceBreaker(uuid, question) {
    const response = await api.post(`/api/rooms/${uuid}/ice-breaker`, { ice_breaker: question });
    return response.data;
  }

  async castVote(uuid, issueId, value) {
    const response = await api.post(`/api/rooms/${uuid}/issues/${issueId}/vote`, { value });
    return response.data;
  }

  async startVoting(uuid, issueId, restrictedCards = null) {
    const response = await api.post(`/api/rooms/${uuid}/issues/${issueId}/start-voting`, { restricted_cards: restrictedCards });
    return response.data;
  }

  async revealVotes(uuid, issueId) {
    const response = await api.post(`/api/rooms/${uuid}/issues/${issueId}/reveal`);
    return response.data;
  }

  async resetVoting(uuid, issueId) {
    const response = await api.post(`/api/rooms/${uuid}/issues/${issueId}/reset`);
    return response.data;
  }

  async updateFinalScore(uuid, issueId, finalScore) {
    const response = await api.post(`/api/rooms/${uuid}/issues/${issueId}/update-score`, { final_score: finalScore });
    return response.data;
  }

  async addIssueFromUrl(uuid, url) {
    const response = await api.post(`/api/rooms/${uuid}/issues/from-url`, { url });
    return response.data;
  }
}

export default new RoomService();