import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet],
  template: '<main><h1>Product Import</h1><router-outlet /></main>',
  styleUrl: './app.component.scss',
})
export class AppComponent {}
