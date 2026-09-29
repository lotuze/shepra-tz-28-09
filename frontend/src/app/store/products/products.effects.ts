import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, map, of, switchMap } from 'rxjs';
import { ProductService } from '../../services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

@Injectable()
export class ProductsEffects {
  private readonly actions = inject(Actions);
  private readonly products = inject(ProductService);
  readonly loadProducts$ = createEffect(() =>
    this.actions.pipe(
      ofType(loadProducts),
      switchMap(({ filters }) =>
        this.products.getProducts(filters).pipe(
          map((response) => loadProductsSuccess({ products: response.data, meta: response.meta })),
          catchError((error: unknown) => of(loadProductsFailure({ error: error instanceof Error ? error.message : 'Не удалось загрузить товары.' }))),
        ),
      ),
    ),
  );

}
