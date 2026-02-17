import axios from 'axios'

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || '').trim()

const api = axios.create({
  baseURL: apiBaseUrl || undefined
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('AUTH_TOKEN')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default api
