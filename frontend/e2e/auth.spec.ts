import { expect, test } from '@playwright/test';
import { demoEmail, demoPassword, failOnBrowserErrors, login } from './helpers';

test.describe('Аутентификация', () => {
  test('защищённый маршрут сохраняет returnUrl', async ({ page }) => {
    await page.goto('/products');
    await expect(page).toHaveURL(/\/login\?returnUrl=%2Fproducts$/);
    await expect(page.getByText('Вход', { exact: true })).toBeVisible();
  });

  test('неверный пароль оставляет пользователя на login', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill(demoEmail);
    await page.getByLabel('Пароль').fill('WrongPassword!');
    await page.getByRole('button', { name: 'Войти' }).click();
    await expect(page.getByRole('alert')).toHaveText('Неверный email или пароль.');
    await expect(page).toHaveURL(/\/login$/);
  });

  test('успешный вход загружает товары без browser errors', async ({ page }) => {
    const browserErrors = failOnBrowserErrors(page);
    await login(page);
    await expect(page.getByText(demoEmail)).toBeVisible();
    await expect(page.getByRole('link', { name: /Открыть карточку товара/ }).first()).toBeVisible();
    expect(browserErrors).toEqual([]);
  });

  test('reload восстанавливает сессию', async ({ page }) => {
    await login(page);
    await page.reload();
    await expect(page).toHaveURL(/\/products$/);
    await expect(page.getByText(demoEmail)).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Товары' })).toBeVisible();
  });

  test('logout очищает сессию и снова закрывает products', async ({ page }) => {
    await login(page);
    await page.getByRole('button', { name: 'Выйти' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await expect.poll(() => page.evaluate(() => localStorage.length)).toBe(0);
    await page.goto('/products');
    await expect(page).toHaveURL(/\/login\?returnUrl=%2Fproducts$/);
  });
});
