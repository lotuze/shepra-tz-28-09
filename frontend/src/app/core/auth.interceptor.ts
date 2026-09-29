import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';

export const authInterceptor: HttpInterceptorFn = (request, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const isApiRequest = request.url.startsWith('/api');
  const isLoginRequest = request.url.startsWith('/api/auth/login');
  const token = isApiRequest && !isLoginRequest ? auth.getToken() : null;
  const authorizedRequest = token === null
    ? request
    : request.clone({ setHeaders: { Authorization: `Bearer ${token}` } });

  return next(authorizedRequest).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 401 && !isLoginRequest) {
        const returnUrl = router.url !== '/login' ? router.url : '/products';
        auth.logout();
        void router.navigate(['/login'], { queryParams: { returnUrl } });
      }
      return throwError(() => error);
    }),
  );
};
