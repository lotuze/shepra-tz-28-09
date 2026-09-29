import { TestBed } from '@angular/core/testing';
import { provideMockActions } from '@ngrx/effects/testing';
import { Observable, Subject, firstValueFrom, of, throwError } from 'rxjs';
import { Action } from '@ngrx/store';
import { beforeEach, describe, expect, it } from 'vitest';
import { ProductListResponse } from '../../models/product.model';
import { ProductService } from '../../services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';
import { ProductsEffects } from './products.effects';

describe('ProductsEffects', () => {
  let actions$: Observable<Action>;
  let actionSubject: Subject<Action>;
  let response$: Observable<ProductListResponse>;
  let effects: ProductsEffects;

  beforeEach(() => {
    actionSubject = new Subject<Action>();
    actions$ = actionSubject.asObservable();
    TestBed.configureTestingModule({
      providers: [
        ProductsEffects,
        provideMockActions(() => actions$),
        { provide: ProductService, useValue: { getProducts: () => response$ } },
      ],
    });
    effects = TestBed.inject(ProductsEffects);
  });

  it('dispatch loadProductsSuccess', async () => {
    const response: ProductListResponse = { data: [], meta: { page: 1, limit: 20, total: 0, totalPages: 0 } };
    response$ = of(response);
    const result = firstValueFrom(effects.loadProducts$);
    actionSubject.next(loadProducts({ filters: { page: 1, limit: 20 } }));
    expect(await result).toEqual(loadProductsSuccess({ products: [], meta: response.meta }));
  });

  it('dispatch loadProductsFailure', async () => {
    response$ = throwError(() => new Error('Недоступно'));
    const result = firstValueFrom(effects.loadProducts$);
    actionSubject.next(loadProducts({ filters: { page: 1, limit: 20 } }));
    expect(await result).toEqual(loadProductsFailure({ error: 'Недоступно' }));
  });
});
