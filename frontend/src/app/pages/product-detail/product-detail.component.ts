import { AsyncPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { catchError, map, of, shareReplay, startWith, switchMap } from 'rxjs';
import { ApiError } from '../../core/api-error';
import { Product } from '../../models/product.model';
import { ProductService } from '../../services/product.service';
import { AttributeLabelPipe } from './attribute-label.pipe';

interface ProductDetailView {
  loading: boolean;
  product: Product | null;
  error: string | null;
  notFound: boolean;
}

@Component({
  selector: 'app-product-detail',
  standalone: true,
  imports: [AsyncPipe, AttributeLabelPipe, MatButtonModule, MatCardModule, MatChipsModule, MatProgressSpinnerModule, RouterLink],
  templateUrl: './product-detail.component.html',
  styleUrl: './product-detail.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductDetailComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly products = inject(ProductService);
  readonly failedImages = new Set<number>();
  readonly viewModel$ = this.route.paramMap.pipe(
    map((params) => Number(params.get('id'))),
    switchMap((id) => this.products.getProduct(id).pipe(
      map((product): ProductDetailView => ({ loading: false, product, error: null, notFound: false })),
      catchError((error: unknown) => of<ProductDetailView>({
        loading: false,
        product: null,
        error: error instanceof Error ? error.message : 'Не удалось загрузить товар.',
        notFound: error instanceof ApiError && error.status === 404,
      })),
      startWith<ProductDetailView>({ loading: true, product: null, error: null, notFound: false }),
    )),
    shareReplay({ bufferSize: 1, refCount: true }),
  );
  markImageFailed(id: number): void { this.failedImages.add(id); }
}
