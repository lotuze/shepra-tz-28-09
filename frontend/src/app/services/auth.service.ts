import { HttpClient } from '@angular/common/http';
import { Injectable, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { AuthUser, LoginCredentials, LoginResponse } from '../models/auth.model';

interface StoredSession {
  token: string;
  user: AuthUser;
  expiresAt: string;
}

const STORAGE_KEY = 'product-import.auth';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly userState = signal<AuthUser | null>(null);
  readonly currentUser = this.userState.asReadonly();

  constructor(private readonly http: HttpClient) {
    this.restoreSession();
  }

  login(credentials: LoginCredentials): Observable<LoginResponse> {
    return this.http.post<LoginResponse>('/api/auth/login', credentials).pipe(
      tap((response) => this.storeSession(response)),
    );
  }

  logout(): void {
    localStorage.removeItem(STORAGE_KEY);
    this.userState.set(null);
  }

  getToken(): string | null {
    const session = this.readSession();
    if (session === null || this.isExpired(session.token, session.expiresAt)) {
      if (session !== null) {
        this.logout();
      }
      return null;
    }
    return session.token;
  }

  isAuthenticated(): boolean {
    return this.getToken() !== null;
  }

  private storeSession(response: LoginResponse): void {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(response));
    this.userState.set(response.user);
  }

  private restoreSession(): void {
    const session = this.readSession();
    if (session === null || this.isExpired(session.token, session.expiresAt)) {
      this.logout();
      return;
    }
    this.userState.set(session.user);
  }

  private readSession(): StoredSession | null {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw === null) {
      return null;
    }
    try {
      const value: unknown = JSON.parse(raw);
      return this.isStoredSession(value) ? value : null;
    } catch {
      return null;
    }
  }

  private isStoredSession(value: unknown): value is StoredSession {
    if (typeof value !== 'object' || value === null) {
      return false;
    }
    const candidate = value as Record<string, unknown>;
    const user = candidate['user'];
    return typeof candidate['token'] === 'string'
      && typeof candidate['expiresAt'] === 'string'
      && typeof user === 'object'
      && user !== null
      && typeof (user as Record<string, unknown>)['id'] === 'number'
      && typeof (user as Record<string, unknown>)['email'] === 'string';
  }

  private isExpired(token: string, fallbackExpiresAt: string): boolean {
    const expiration = this.readJwtExpiration(token) ?? Date.parse(fallbackExpiresAt);
    return !Number.isFinite(expiration) || expiration <= Date.now();
  }

  private readJwtExpiration(token: string): number | null {
    const payload = token.split('.')[1];
    if (payload === undefined) {
      return null;
    }
    try {
      const normalized = payload.replace(/-/g, '+').replace(/_/g, '/');
      const decoded: unknown = JSON.parse(atob(normalized));
      if (typeof decoded !== 'object' || decoded === null) {
        return null;
      }
      const expiration = (decoded as Record<string, unknown>)['exp'];
      return typeof expiration === 'number' ? expiration * 1000 : null;
    } catch {
      return null;
    }
  }
}
