import { Component } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatToolbarModule } from '@angular/material/toolbar';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from './services/auth.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [MatButtonModule, MatToolbarModule, RouterLink, RouterLinkActive, RouterOutlet],
  template: `
    <mat-toolbar color="primary">
      <div class="toolbar-inner">
        <a class="brand" routerLink="/products" aria-label="Перейти к списку товаров">Импорт товаров</a>
        @if (auth.currentUser(); as user) {
          <nav aria-label="Основная навигация">
            <a mat-button routerLink="/products" routerLinkActive="active">Товары</a>
            <a mat-button routerLink="/import" routerLinkActive="active">Импорт</a>
          </nav>
          <div class="account">
            <span class="user-email">{{ user.email }}</span>
            <button class="logout-button" mat-stroked-button type="button" (click)="logout()">Выйти</button>
          </div>
        }
      </div>
    </mat-toolbar>
    <main><router-outlet /></main>
  `,
  styleUrl: './app.component.scss',
})
export class AppComponent {
  constructor(readonly auth: AuthService, private readonly router: Router) {}

  logout(): void {
    this.auth.logout();
    void this.router.navigate(['/login']);
  }
}
