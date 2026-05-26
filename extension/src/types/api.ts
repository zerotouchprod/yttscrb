// === Request ===

export interface TranscribeRequest {
  youtube_url: string;
}

// === Response: POST /api/transcribe (202 Accepted — new task) ===

export interface TranscribeCreatedResponse {
  task_id: string;
  status: 'pending';
  youtube_url: string;
  created_at: string;
  _links: {
    status: string; // e.g. "/api/transcribe/{id}"
  };
}

// === Response: POST /api/transcribe (200 OK — deduplicated, already completed) ===
// === Response: GET /api/transcribe/{id} (status === 'completed') ===

export interface SummaryKeyPoint {
  timecode: string;
  title: string;
  details: string;
}

export interface ClickbaitVerdict {
  score: number;
  comment: string;
}

export interface Summary {
  introduction: string;
  key_points: SummaryKeyPoint[];
  conclusion: string | null;
  clickbait_verdict: ClickbaitVerdict | null;
}

export interface TranscribeTaskResponse {
  task_id: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  youtube_url: string;
  video_id?: string;
  title?: string | null;
  created_at: string;
  duration_sec?: number;
  estimated_completion_sec?: number;
  result?: {
    summary: Summary;
  };
  error_message?: string | null;
  completed_at?: string;
  failed_at?: string;
  _links: {
    self?: string;
    status?: string;
    public_page?: string; // e.g. "/v/slug"
    download_txt?: string;
  };
}

// === Error Response ===

export interface ApiError {
  error: {
    code: string;
    message: string;
    details: Record<string, unknown>;
  };
}

// === Union type for transcribe POST response ===

export type TranscribePostResponse = TranscribeCreatedResponse | TranscribeTaskResponse;

// === Messaging types (content-script <-> background <-> popup) ===

export interface SummarizeRequestMessage {
  type: 'SUMMARIZE_REQUEST';
  youtubeUrl: string;
}

export interface SummarizeProgressMessage {
  type: 'SUMMARIZE_PROGRESS';
  taskId: string;
  status: 'pending' | 'processing';
}

export interface SummarizeSuccessMessage {
  type: 'SUMMARIZE_SUCCESS';
  taskId: string;
  data: TranscribeTaskResponse;
}

export interface SummarizeErrorMessage {
  type: 'SUMMARIZE_ERROR';
  error: string;
}

export type BackgroundMessage =
  | SummarizeRequestMessage
  | SummarizeProgressMessage
  | SummarizeSuccessMessage
  | SummarizeErrorMessage;

export interface GetActiveTabUrlMessage {
  type: 'GET_ACTIVE_TAB_URL';
}

export interface ActiveTabUrlResponse {
  type: 'ACTIVE_TAB_URL';
  url: string | null;
}

export type PopupMessage = GetActiveTabUrlMessage;
export type PopupResponse = ActiveTabUrlResponse;
