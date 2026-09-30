import { expect, Page } from '@playwright/test';

export const demoEmail = 'admin@example.com';
export const demoPassword = 'ChangeMe123!';

export async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email').fill(demoEmail);
  await page.getByLabel('Пароль').fill(demoPassword);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(/\/products$/);
  await expect(page.getByRole('heading', { name: 'Товары' })).toBeVisible();
}

export function failOnBrowserErrors(page: Page): string[] {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().toLowerCase().includes('favicon')) {
      errors.push(`console: ${message.text()}`);
    }
  });
  return errors;
}
