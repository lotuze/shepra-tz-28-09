export type ImportStatus =
  | 'queued'
  | 'processing'
  | 'completed'
  | 'completed_with_errors'
  | 'failed';

export interface ImportError {
  id: number;
  rowNumber: number | null;
  externalCode: string | null;
  severity: 'error' | 'warning';
  message: string;
  rawData: Record<string, unknown> | null;
  createdAt: string;
}

export interface ImportJob {
  id: number;
  originalFilename: string;
  status: ImportStatus;
  totalRows: number;
  processedRows: number;
  successfulRows: number;
  failedRows: number;
  progress: number;
  errors: ImportError[];
  errorsTotal: number;
  createdAt: string;
  startedAt: string | null;
  finishedAt: string | null;
  fatalError: string | null;
}

export interface ImportSubmissionResponse {
  id: number;
  status: ImportStatus;
  statusUrl: string;
}
