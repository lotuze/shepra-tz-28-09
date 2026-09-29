import { createFeatureSelector, createSelector } from '@ngrx/store';
import { ProductsState } from './products.reducer';

export const selectProductsState = createFeatureSelector<ProductsState>('products');
export const selectAllProducts = createSelector(selectProductsState, (state) => state.products);
export const selectProductsLoading = createSelector(selectProductsState, (state) => state.loading);
export const selectTotalPages = createSelector(selectProductsState, (state) => state.meta.totalPages);
export const selectProductsError = createSelector(selectProductsState, (state) => state.error);
export const selectProductsMeta = createSelector(selectProductsState, (state) => state.meta);
export const selectProductsFilters = createSelector(selectProductsState, (state) => state.filters);
