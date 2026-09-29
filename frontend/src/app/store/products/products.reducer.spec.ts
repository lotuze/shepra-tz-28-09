import { describe, expect, it } from 'vitest';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';
import { initialProductsState, productsReducer } from './products.reducer';

describe('productsReducer', () => {
  it('обрабатывает загрузку, успех и ошибку', () => {
    const loading = productsReducer(initialProductsState, loadProducts({ filters: { page: 2, limit: 10 } }));
    expect(loading.loading).toBe(true);
    expect(loading.filters.page).toBe(2);

    const success = productsReducer(loading, loadProductsSuccess({
      products: [{ id: 1, externalCode: 'A', name: 'Товар', description: 'Описание', price: '10.00', discount: '0.00' }],
      meta: { page: 2, limit: 10, total: 11, totalPages: 2 },
    }));
    expect(success.products).toHaveLength(1);
    expect(success.loading).toBe(false);

    const failure = productsReducer(success, loadProductsFailure({ error: 'Ошибка' }));
    expect(failure.error).toBe('Ошибка');
    expect(failure.products).toEqual([]);
  });
});
