import { Routes } from '@angular/router';

export const routes: Routes = [
  { path: 'products', loadComponent: () => import('./pages/product-list/product-list.component').then((module) => module.ProductListComponent) },
  { path: 'products/:id', loadComponent: () => import('./pages/product-detail/product-detail.component').then((module) => module.ProductDetailComponent) },
  { path: 'import', loadComponent: () => import('./pages/import/import.component').then((module) => module.ImportComponent) },
  { path: '', pathMatch: 'full', redirectTo: 'products' },
  { path: '**', redirectTo: 'products' },
];
