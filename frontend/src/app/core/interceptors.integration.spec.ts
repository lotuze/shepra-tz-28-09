import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthService } from '../services/auth.service';
import { ApiError } from './api-error';
import { authInterceptor } from './auth.interceptor';
import { httpErrorInterceptor } from './http-error.interceptor';

describe('auth и HTTP error interceptors', () => {
  const auth = { getToken: vi.fn(), logout: vi.fn() };
  let http: HttpTestingController;
  let router: Router;

  beforeEach(() => {
    auth.getToken.mockReturnValue('jwt-token');
    auth.logout.mockClear();
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([httpErrorInterceptor, authInterceptor])),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: auth },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);
  });

  afterEach(() => http.verify());

  it('добавляет token, обрабатывает raw 401 и затем нормализует ошибку', () => {
    let receivedError: unknown;
    TestBed.inject(HttpClient).get('/api/products').subscribe({
      error: (error: unknown) => { receivedError = error; },
    });

    const request = http.expectOne('/api/products');
    expect(request.request.headers.get('Authorization')).toBe('Bearer jwt-token');
    request.flush({ error: 'Unauthorized.' }, { status: 401, statusText: 'Unauthorized' });

    expect(auth.logout).toHaveBeenCalledOnce();
    expect(router.navigate).toHaveBeenCalledWith(['/login'], { queryParams: { returnUrl: '/' } });
    expect(receivedError).toBeInstanceOf(ApiError);
    expect((receivedError as ApiError).status).toBe(401);
    expect((receivedError as ApiError).message).toBe('Необходима авторизация.');
  });
});
