import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthService } from '../services/auth.service';
import { authGuard } from './auth.guard';

describe('authGuard', () => {
  const auth = { isAuthenticated: vi.fn() };

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideRouter([]), { provide: AuthService, useValue: auth }] });
  });

  it('разрешает переход авторизованному пользователю', () => {
    auth.isAuthenticated.mockReturnValue(true);
    const result = TestBed.runInInjectionContext(() => authGuard({} as never, { url: '/products' } as never));
    expect(result).toBe(true);
  });

  it('перенаправляет на login с returnUrl', () => {
    auth.isAuthenticated.mockReturnValue(false);
    const router = TestBed.inject(Router);
    const result = TestBed.runInInjectionContext(() => authGuard({} as never, { url: '/import' } as never));
    expect(router.serializeUrl(result as ReturnType<Router['createUrlTree']>)).toBe('/login?returnUrl=%2Fimport');
  });
});
