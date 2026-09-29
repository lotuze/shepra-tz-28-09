import { Component } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatToolbarModule } from '@angular/material/toolbar';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

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
      <nav aria-label="Основная навигация">
        <a mat-button routerLink="/products" routerLinkActive="active">Товары</a>
        <a mat-button routerLink="/import" routerLinkActive="active">Импорт</a>
      </nav>
    </mat-toolbar>
    <main><router-outlet /></main>
  `,
  styleUrl: './app.component.scss',
})
export class AppComponent {}
