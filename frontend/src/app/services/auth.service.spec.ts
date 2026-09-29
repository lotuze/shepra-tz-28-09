import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { LoginResponse } from '../models/auth.model';
import { AuthService } from './auth.service';

const response: LoginResponse = {
  token: tokenWithExpiration(Math.floor(Date.now() / 1000) + 3600),
  user: { id: 1, email: 'admin@example.com' },
  expiresAt: new Date(Date.now() + 3600_000).toISOString(),
};

describe('AuthService', () => {
  let service: AuthService;
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('login сохраняет token и пользователя, logout очищает их', () => {
    service.login({ email: 'admin@example.com', password: 'secret' }).subscribe();
    const request = http.expectOne('/api/auth/login');
    expect(request.request.body).toEqual({ email: 'admin@example.com', password: 'secret' });
    request.flush(response);

    expect(service.getToken()).toBe(response.token);
    expect(service.currentUser()).toEqual(response.user);
    service.logout();
    expect(service.getToken()).toBeNull();
    expect(service.currentUser()).toBeNull();
  });

  it('восстанавливает непросроченную сессию', () => {
    localStorage.setItem('product-import.auth', JSON.stringify(response));
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    const restored = TestBed.inject(AuthService);
    expect(restored.currentUser()).toEqual(response.user);
    expect(restored.isAuthenticated()).toBe(true);
  });
});

function tokenWithExpiration(exp: number): string {
  const payload = btoa(JSON.stringify({ exp })).replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');
  return `header.${payload}.signature`;
}
