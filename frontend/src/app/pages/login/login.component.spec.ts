import { TestBed } from '@angular/core/testing';
import { provideNoopAnimations } from '@angular/platform-browser/animations';
import { provideRouter, Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { LoginResponse } from '../../models/auth.model';
import { AuthService } from '../../services/auth.service';
import { LoginComponent } from './login.component';

describe('LoginComponent', () => {
  const result: LoginResponse = {
    token: 'token',
    user: { id: 1, email: 'admin@example.com' },
    expiresAt: '2099-01-01T00:00:00+00:00',
  };
  const auth = { login: vi.fn() };
  let router: Router;

  beforeEach(async () => {
    auth.login.mockReset();
    await TestBed.configureTestingModule({
      imports: [LoginComponent],
      providers: [provideNoopAnimations(), provideRouter([]), { provide: AuthService, useValue: auth }],
    }).compileComponents();
    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
  });

  it('после успешного входа переходит к товарам', () => {
    auth.login.mockReturnValue(of(result));
    const fixture = TestBed.createComponent(LoginComponent);
    fixture.componentInstance.form.setValue({ email: 'admin@example.com', password: 'ChangeMe123!' });
    fixture.componentInstance.submit();
    expect(auth.login).toHaveBeenCalledWith({ email: 'admin@example.com', password: 'ChangeMe123!' });
    expect(router.navigateByUrl).toHaveBeenCalledWith('/products');
  });

  it('после входа возвращает пользователя на returnUrl', async () => {
    vi.mocked(router.navigateByUrl).mockRestore();
    await router.navigateByUrl('/?returnUrl=%2Fimport');
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    auth.login.mockReturnValue(of(result));
    const fixture = TestBed.createComponent(LoginComponent);
    fixture.componentInstance.form.setValue({ email: 'admin@example.com', password: 'ChangeMe123!' });
    fixture.componentInstance.submit();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/import');
  });

  it('показывает ошибку неверных данных', () => {
    auth.login.mockReturnValue(throwError(() => new Error('Unauthorized')));
    const fixture = TestBed.createComponent(LoginComponent);
    fixture.componentInstance.form.setValue({ email: 'admin@example.com', password: 'wrong' });
    fixture.componentInstance.submit();
    expect(fixture.componentInstance.error).toBe('Неверный email или пароль.');
    expect(fixture.componentInstance.loading).toBe(false);
  });
});
