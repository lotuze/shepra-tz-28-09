import 'zone.js';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';
import { bootstrapApplication } from '@angular/platform-browser';
import { provideRouter } from '@angular/router';
import { provideEffects } from '@ngrx/effects';
import { provideStore } from '@ngrx/store';
import { provideStoreDevtools } from '@ngrx/store-devtools';
import { AppComponent } from './app/app.component';
import { routes } from './app/app.routes';
import { httpErrorInterceptor } from './app/core/http-error.interceptor';
import { authInterceptor } from './app/core/auth.interceptor';
import { ProductsEffects } from './app/store/products/products.effects';
import { productsReducer } from './app/store/products/products.reducer';
import { environment } from './environments/environment';

bootstrapApplication(AppComponent, {
  providers: [
    provideRouter(routes),
    provideHttpClient(withInterceptors([httpErrorInterceptor, authInterceptor])),
    provideAnimationsAsync(),
    provideStore({ products: productsReducer }),
    provideEffects(ProductsEffects),
    ...(!environment.production ? [provideStoreDevtools({ maxAge: 25 })] : []),
  ],
}).catch((error: unknown) => console.error(error));
