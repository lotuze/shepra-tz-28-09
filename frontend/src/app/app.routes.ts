import { Routes } from '@angular/router';
import { authGuard, loginGuard } from './core/auth.guard';

export const routes: Routes = [
  { path: 'login', canActivate: [loginGuard], loadComponent: () => import('./pages/login/login.component').then((module) => module.LoginComponent) },
  { path: 'products', canActivate: [authGuard], loadComponent: () => import('./pages/product-list/product-list.component').then((module) => module.ProductListComponent) },
  { path: 'products/:id', canActivate: [authGuard], loadComponent: () => import('./pages/product-detail/product-detail.component').then((module) => module.ProductDetailComponent) },
  { path: 'import', canActivate: [authGuard], loadComponent: () => import('./pages/import/import.component').then((module) => module.ImportComponent) },
  { path: '', pathMatch: 'full', redirectTo: 'products' },
  { path: '**', redirectTo: 'products' },
];
