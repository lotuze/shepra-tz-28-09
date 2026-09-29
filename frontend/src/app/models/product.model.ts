export interface ProductAttribute {
  key: string;
  value: string | null;
}

export interface ProductImage {
  id: number;
  url: string;
  path: string | null;
  contentUrl: string;
}

export interface Product {
  id: number;
  externalCode: string;
  name: string;
  description: string;
  price: string;
  discount: string;
  attributes?: ProductAttribute[];
  images?: ProductImage[];
}

export interface PaginationMeta {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface ProductListResponse {
  data: Product[];
  meta: PaginationMeta;
}

export interface ProductFilters {
  page: number;
  limit: number;
  name?: string;
  minPrice?: string;
  maxPrice?: string;
}
