import { createAction, props } from '@ngrx/store';
import { Product, ProductFilters, PaginationMeta } from '../../models/product.model';

export const loadProducts = createAction(
  '[Products] Load Products',
  props<{ filters: ProductFilters }>(),
);

export const loadProductsSuccess = createAction(
  '[Products] Load Products Success',
  props<{ products: Product[]; meta: PaginationMeta }>(),
);

export const loadProductsFailure = createAction(
  '[Products] Load Products Failure',
  props<{ error: string }>(),
);
