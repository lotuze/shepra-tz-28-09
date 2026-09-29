import { TestBed } from '@angular/core/testing';
import { provideNoopAnimations } from '@angular/platform-browser/animations';
import { provideRouter, Router } from '@angular/router';
import { Store } from '@ngrx/store';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Product } from '../../models/product.model';
import { ProductListComponent } from './product-list.component';
import { loadProducts } from '../../store/products/products.actions';
import { selectAllProducts, selectProductsError, selectProductsLoading, selectProductsMeta, selectTotalPages } from '../../store/products/products.selectors';

describe('ProductListComponent', () => {
  const product: Product = { id: 7, externalCode: 'EXT-7', name: 'Тестовый товар', description: 'Описание', price: '100.00', discount: '10.00' };
  const dispatch = vi.fn();
  let router: Router;

  beforeEach(async () => {
    dispatch.mockClear();
    const store = {
      dispatch,
      select: vi.fn((selector: unknown) => {
        if (selector === selectAllProducts) { return of([product]); }
        if (selector === selectProductsLoading) { return of(false); }
        if (selector === selectProductsError) { return of(null); }
        if (selector === selectProductsMeta) { return of({ page: 1, limit: 20, total: 1, totalPages: 1 }); }
        if (selector === selectTotalPages) { return of(1); }
        throw new Error('Unexpected selector.');
      }),
    } as unknown as Store;

    await TestBed.configureTestingModule({
      imports: [ProductListComponent],
      providers: [
        provideNoopAnimations(),
        provideRouter([]),
        { provide: Store, useValue: store },
      ],
    }).compileComponents();
    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);
  });

  it('dispatch loadProducts при инициализации', () => {
    const fixture = TestBed.createComponent(ProductListComponent);
    fixture.detectChanges();
    expect(dispatch).toHaveBeenCalledWith(loadProducts({ filters: { page: 1, limit: 20 } }));
  });

  it('переходит в карточку по клику и Enter на строке', () => {
    const fixture = TestBed.createComponent(ProductListComponent);
    fixture.detectChanges();
    const row = fixture.nativeElement.querySelector('tr[mat-row]') as HTMLTableRowElement;

    row.click();
    expect(router.navigate).toHaveBeenLastCalledWith(['/products', 7]);

    vi.mocked(router.navigate).mockClear();
    row.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    expect(router.navigate).toHaveBeenLastCalledWith(['/products', 7]);
    expect(row.tabIndex).toBe(0);
    expect(row.getAttribute('aria-label')).toBe('Открыть карточку товара Тестовый товар');
  });
});
