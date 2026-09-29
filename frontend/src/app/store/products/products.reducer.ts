import { createReducer, on } from '@ngrx/store';
import { PaginationMeta, Product, ProductFilters } from '../../models/product.model';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

export interface ProductsState {
  products: Product[];
  loading: boolean;
  error: string | null;
  meta: PaginationMeta;
  filters: ProductFilters;
}

export const initialProductsState: ProductsState = {
  products: [],
  loading: false,
  error: null,
  meta: { page: 1, limit: 20, total: 0, totalPages: 0 },
  filters: { page: 1, limit: 20 },
};

export const productsReducer = createReducer(
  initialProductsState,
  on(loadProducts, (state, { filters }): ProductsState => ({ ...state, filters, loading: true, error: null })),
  on(loadProductsSuccess, (state, { products, meta }): ProductsState => ({ ...state, products, meta, loading: false })),
  on(loadProductsFailure, (state, { error }): ProductsState => ({ ...state, products: [], loading: false, error })),
);
