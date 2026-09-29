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
      <a class="brand" routerLink="/products" aria-label="Перейти к списку товаров">
        <span>Импорт товаров</span>
      </a>
      <span class="spacer"></span>
      @if (auth.currentUser(); as user) {
        <nav aria-label="Основная навигация">
          <a mat-button routerLink="/products" routerLinkActive="active">Товары</a>
          <a mat-button routerLink="/import" routerLinkActive="active">Импорт</a>
        </nav>
        <span class="user-email">{{ user.email }}</span>
        <button mat-button type="button" (click)="logout()">Выйти</button>
      }
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
