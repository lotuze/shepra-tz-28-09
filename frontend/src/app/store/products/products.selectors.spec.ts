import { describe, expect, it } from 'vitest';
import { ProductsState } from './products.reducer';
import { selectAllProducts, selectProductsLoading, selectTotalPages } from './products.selectors';

describe('products selectors', () => {
  const state: ProductsState = {
    products: [{ id: 1, externalCode: 'A', name: 'Товар', description: 'Описание', price: '10.00', discount: '0.00' }],
    loading: true, error: null, filters: { page: 1, limit: 20 }, meta: { page: 1, limit: 20, total: 1, totalPages: 3 },
  };

  it('возвращает товары, loading и число страниц', () => {
    expect(selectAllProducts.projector(state)).toEqual(state.products);
    expect(selectProductsLoading.projector(state)).toBe(true);
    expect(selectTotalPages.projector(state)).toBe(3);
  });
});
