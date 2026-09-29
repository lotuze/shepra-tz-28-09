import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { catchError, throwError } from 'rxjs';
import { ApiError } from './api-error';

export const httpErrorInterceptor: HttpInterceptorFn = (request, next) =>
  next(request).pipe(
    catchError((response: unknown) => {
      if (!(response instanceof HttpErrorResponse)) {
        return throwError(() => response);
      }

      const backendMessage = extractBackendMessage(response.error);
      let message = backendMessage ?? response.message;
      if (response.status === 401) {
        message = 'Необходима авторизация.';
      } else if (response.status >= 500) {
        message = 'Сервер временно недоступен. Попробуйте позже.';
      }

      return throwError(() => new ApiError(message, response.status));
    }),
  );

function extractBackendMessage(payload: unknown): string | null {
  if (typeof payload !== 'object' || payload === null || !('error' in payload)) {
    return null;
  }

  return typeof payload.error === 'string' ? payload.error : null;
}
