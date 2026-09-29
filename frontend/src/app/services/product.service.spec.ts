import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { describe, expect, it, beforeEach, afterEach } from 'vitest';
import { ProductService } from './product.service';

describe('ProductService', () => {
  let service: ProductService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [ProductService, provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(ProductService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('формирует query parameters', () => {
    service.getProducts({ page: 2, limit: 50, name: ' Бермуды ', minPrice: '500', maxPrice: '1500.00' }).subscribe();
    const request = http.expectOne((candidate) => candidate.url === '/api/products');
    expect(request.request.params.get('page')).toBe('2');
    expect(request.request.params.get('limit')).toBe('50');
    expect(request.request.params.get('name')).toBe('Бермуды');
    expect(request.request.params.get('minPrice')).toBe('500');
    expect(request.request.params.get('maxPrice')).toBe('1500.00');
    request.flush({ data: [], meta: { page: 2, limit: 50, total: 0, totalPages: 0 } });
  });
});
