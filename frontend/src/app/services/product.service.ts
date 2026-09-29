import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { Product, ProductFilters, ProductListResponse } from '../models/product.model';

@Injectable({ providedIn: 'root' })
export class ProductService {
  constructor(private readonly http: HttpClient) {}

  getProducts(filters: ProductFilters): Observable<ProductListResponse> {
    let params = new HttpParams()
      .set('page', filters.page)
      .set('limit', filters.limit);

    for (const key of ['name', 'minPrice', 'maxPrice'] as const) {
      const value = filters[key]?.trim();
      if (value) {
        params = params.set(key, value);
      }
    }

    return this.http.get<ProductListResponse>('/api/products', { params });
  }

  getProduct(id: number): Observable<Product> {
    return this.http.get<Product>(`/api/products/${id}`);
  }
}
