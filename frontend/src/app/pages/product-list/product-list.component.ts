import { AsyncPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, OnInit, inject } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTableModule } from '@angular/material/table';
import { Router, RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { combineLatest } from 'rxjs';
import { ProductFilters } from '../../models/product.model';
import { loadProducts } from '../../store/products/products.actions';
import { selectAllProducts, selectProductsError, selectProductsLoading, selectProductsMeta, selectTotalPages } from '../../store/products/products.selectors';

@Component({
  selector: 'app-product-list',
  standalone: true,
  imports: [AsyncPipe, MatButtonModule, MatCardModule, MatFormFieldModule, MatInputModule, MatPaginatorModule, MatProgressSpinnerModule, MatTableModule, ReactiveFormsModule, RouterLink],
  templateUrl: './product-list.component.html',
  styleUrl: './product-list.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductListComponent implements OnInit {
  private readonly store = inject(Store);
  private readonly router = inject(Router);
  readonly displayedColumns = ['name', 'externalCode', 'price', 'discount'];
  readonly filterForm = new FormGroup({
    name: new FormControl('', { nonNullable: true }),
    minPrice: new FormControl('', { nonNullable: true }),
    maxPrice: new FormControl('', { nonNullable: true }),
  });
  readonly viewModel$ = combineLatest({
    products: this.store.select(selectAllProducts),
    loading: this.store.select(selectProductsLoading),
    error: this.store.select(selectProductsError),
    meta: this.store.select(selectProductsMeta),
    totalPages: this.store.select(selectTotalPages),
  });

  private filters: ProductFilters = { page: 1, limit: 20 };

  ngOnInit(): void { this.load(this.filters); }

  applyFilters(): void {
    const values = this.filterForm.getRawValue();
    this.filters = {
      page: 1,
      limit: this.filters.limit,
      ...(values.name.trim() && { name: values.name.trim() }),
      ...(values.minPrice.trim() && { minPrice: values.minPrice.trim() }),
      ...(values.maxPrice.trim() && { maxPrice: values.maxPrice.trim() }),
    };
    this.load(this.filters);
  }

  resetFilters(): void {
    this.filterForm.reset();
    this.filters = { page: 1, limit: this.filters.limit };
    this.load(this.filters);
  }

  changePage(event: PageEvent): void {
    this.filters = { ...this.filters, page: event.pageIndex + 1, limit: event.pageSize };
    this.load(this.filters);
  }

  retry(): void { this.load(this.filters); }

  openProduct(id: number): void {
    void this.router.navigate(['/products', id]);
  }

  private load(filters: ProductFilters): void { this.store.dispatch(loadProducts({ filters })); }
}
