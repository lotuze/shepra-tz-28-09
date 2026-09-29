export interface AuthUser {
  id: number;
  email: string;
}

export interface LoginResponse {
  token: string;
  user: AuthUser;
  expiresAt: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
}
