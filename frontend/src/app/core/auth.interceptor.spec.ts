import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthService } from '../services/auth.service';
import { authInterceptor } from './auth.interceptor';

describe('authInterceptor', () => {
  const auth = { getToken: vi.fn(), logout: vi.fn() };
  let http: HttpTestingController;
  let router: Router;

  beforeEach(() => {
    auth.getToken.mockReturnValue('jwt-token');
    auth.logout.mockClear();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting(), provideRouter([]), { provide: AuthService, useValue: auth }],
    });
    http = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);
  });

  it('добавляет Bearer token к API запросу', () => {
    TestBed.inject(HttpClient).get('/api/products').subscribe();
    const request = http.expectOne('/api/products');
    expect(request.request.headers.get('Authorization')).toBe('Bearer jwt-token');
    request.flush({ data: [] });
  });

  it('при 401 очищает сессию и переводит на login', () => {
    TestBed.inject(HttpClient).get('/api/products').subscribe({ error: () => undefined });
    http.expectOne('/api/products').flush({ error: 'Unauthorized.' }, { status: 401, statusText: 'Unauthorized' });
    expect(auth.logout).toHaveBeenCalledOnce();
    expect(router.navigate).toHaveBeenCalledWith(['/login'], { queryParams: { returnUrl: '/' } });
  });
});
