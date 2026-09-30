import path from 'node:path';
import { expect, test } from '@playwright/test';
import { login } from './helpers';

test.describe('Импорт', () => {
  test.beforeEach(async ({ page }) => login(page));

  test('валидный XLSX отправляется и доходит до terminal status', async ({ page }) => {
    let pollingResponses = 0;
    page.on('response', (response) => {
      if (/\/api\/imports\/\d+$/.test(new URL(response.url()).pathname)) {
        pollingResponses += 1;
      }
    });

    await page.getByRole('link', { name: 'Импорт', exact: true }).click();
    await expect(page).toHaveURL(/\/import$/);
    const fileInput = page.getByLabel('Файл XLSX');
    await fileInput.setInputFiles(path.join(__dirname, 'fixtures/import-e2e.xlsx'));
    const start = page.getByRole('button', { name: 'Запустить импорт' });
    await expect(start).toBeEnabled();

    const accepted = page.waitForResponse((response) => response.url().endsWith('/api/imports') && response.request().method() === 'POST');
    await start.click();
    expect((await accepted).status()).toBe(202);
    await expect(page.getByRole('progressbar')).toBeVisible();
    await expect(page.getByText('Завершён', { exact: true })).toBeVisible({ timeout: 20_000 });
    await expect(page.getByTestId('import-progress')).toHaveText('100%');
    await expect(page.getByTestId('total-rows')).toHaveText('1');
    await expect(page.getByTestId('processed-rows')).toHaveText('1');
    await expect(page.getByTestId('successful-rows')).toHaveText('1');
    await expect(page.getByTestId('failed-rows')).toHaveText('0');
    expect(pollingResponses).toBeGreaterThan(0);
  });

  test('неверное расширение отклоняется до HTTP upload', async ({ page }) => {
    let uploadRequests = 0;
    page.on('request', (request) => {
      if (request.url().endsWith('/api/imports') && request.method() === 'POST') {
        uploadRequests += 1;
      }
    });
    await page.goto('/import');
    await page.getByLabel('Файл XLSX').setInputFiles({
      name: 'products.csv',
      mimeType: 'text/csv',
      buffer: Buffer.from('not,xlsx'),
    });
    await expect(page.getByRole('alert')).toHaveText('Выберите файл с расширением .xlsx.');
    await expect(page.getByRole('button', { name: 'Запустить импорт' })).toBeDisabled();
    expect(uploadRequests).toBe(0);
  });
});
