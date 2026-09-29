import { describe, expect, it } from 'vitest';
import { authGuard } from './core/auth.guard';
import { routes } from './app.routes';

describe('routes', () => {
  it('защищает products, product detail и import', () => {
    for (const path of ['products', 'products/:id', 'import']) {
      const route = routes.find((candidate) => candidate.path === path);
      expect(route?.canActivate).toContain(authGuard);
    }
  });
});
