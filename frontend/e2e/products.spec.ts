import { expect, test } from '@playwright/test';
import { login } from './helpers';

test.describe('Товары', () => {
  test.beforeEach(async ({ page }) => login(page));

  test('показывает fixtures, фильтрует и переключает серверную страницу', async ({ page }) => {
    await expect(page.getByText('SKU-0030')).toBeVisible();

    await page.getByLabel('Поиск по названию').fill('Бермуды 01');
    const filtered = page.waitForResponse((response) => {
      const url = new URL(response.url());
      return url.pathname === '/api/products' && url.searchParams.get('name') === 'Бермуды 01';
    });
    await page.getByRole('button', { name: 'Применить' }).click();
    await filtered;
    await expect(page.getByRole('link', { name: 'Открыть карточку товара Бермуды 01' })).toBeVisible();
    await expect(page.getByText('SKU-0001')).toBeVisible();

    await page.getByRole('button', { name: 'Сбросить' }).click();
    const secondPage = page.waitForResponse((response) => {
      const url = new URL(response.url());
      return url.pathname === '/api/products' && url.searchParams.get('page') === '2';
    });
    await page.getByRole('button', { name: 'Next page' }).click();
    await secondPage;
    await expect(page.getByText('SKU-0010')).toBeVisible();
  });

  test('клик по строке открывает карточку и возврат работает', async ({ page }) => {
    await page.getByLabel('Поиск по названию').fill('Бермуды 01');
    await page.getByRole('button', { name: 'Применить' }).click();
    await page.getByRole('link', { name: 'Открыть карточку товара Бермуды 01' }).click();

    await expect(page).toHaveURL(/\/products\/\d+$/);
    await expect(page.getByRole('heading', { name: 'Бермуды 01' })).toBeVisible();
    await expect(page.getByText('425.00 ₽')).toBeVisible();
    await expect(page.getByText('Размер')).toBeVisible();
    await page.getByRole('link', { name: '< К списку' }).click();
    await expect(page).toHaveURL(/\/products$/);
  });
});
