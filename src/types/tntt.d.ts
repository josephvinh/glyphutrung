/**
 * TNTT Application Type Definitions
 * Cung cấp type safety cho JavaScript modules hiện tại
 */

// ============================================================
// CORE TYPES
// ============================================================

export interface Member {
  id: number;
  code: string;
  holyName: string;
  fullName: string;
  phone: string;
  birthDate: string | null;
  gender: number;
  address: string | null;
  fatherName: string | null;
  fatherPhone: string | null;
  motherName: string | null;
  motherPhone: string | null;
  roleCode: string;
  titleId: number | null;
  status: MemberStatus;
  classId: number | null;
  blockId: number | null;
}

export type MemberStatus = 'chờ duyệt' | 'đang phục vụ' | 'từ chối' | 'đã nghỉ';

export interface Student {
  id: number;
  code: string;
  holyName: string;
  name: string;
  gender: number;
  birthDate: string;
  address: string | null;
  fatherName: string | null;
  fatherPhone: string | null;
  motherName: string | null;
  motherPhone: string | null;
  classId: number | null;
  className: string | null;
  blockId: number | null;
  blockName: string | null;
  enrollStatus: 'active' | 'inactive';
}

export interface Block {
  id: number;
  name: string;
  sortOrder: number;
  classCount?: number;
}

export interface Class {
  id: number;
  name: string;
  blockId: number;
  blockName?: string;
  sortOrder: number;
  studentCount?: number;
  nextClassId: number | null;
  isFinal: boolean;
}

export interface Program {
  id: number;
  name: string;
  type: 'chiến dịch' | 'sinh hoạt';
  date: string;
  startTime: string;
  endTime: string;
  status: 'kích hoạt' | 'tắt';
  countForAttendance: boolean;
  attendanceCount?: number;
  totalStudents?: number;
}

export interface Attendance {
  id: number;
  programId: number;
  studentId: number;
  status: 'C' | 'M' | 'V' | 'P' | null;
  checkedAt: string | null;
  checkedBy: number | null;
}

export interface Score {
  id: number;
  studentId: number;
  programId: number;
  value: number | null;
  updatedAt: string | null;
}

export interface Announcement {
  id: number;
  title: string;
  body: string;
  status: 'nháp' | 'published';
  createdAt: string;
  createdBy: number;
  createdByName: string;
  publishedAt: string | null;
}

export interface LeaveRequest {
  id: number;
  studentId: number;
  studentName: string;
  className: string;
  reason: string;
  status: 'chờ duyệt' | 'đã duyệt' | 'từ chòi';
  requestedAt: string;
  requestedBy: number;
  resolvedAt: string | null;
  resolvedBy: number | null;
  rejectReason: string | null;
}

export interface PersonalNote {
  id: number;
  title: string;
  note: string;
  remindAt: string;
  allDay: boolean;
  done: boolean;
}

export interface ActivityLog {
  id: number;
  at: string;
  ts: number;
  actor: string;
  action: string;
  module: string;
  what: string;
  detail: string;
}

// ============================================================
// API RESPONSE TYPES
// ============================================================

export interface ApiResponse<T = unknown> {
  ok: boolean;
  error?: string;
  data?: T;
}

export interface PaginatedResponse<T> {
  ok: boolean;
  data: T[];
  pagination: {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
    hasNext: boolean;
    hasPrev: boolean;
  };
}

export interface LoginResponse {
  ok: boolean;
  token?: string;
  member?: Member;
  error?: string;
}

export interface DataPartResponse {
  students: Student[];
  programs: Program[];
  enrollments: Record<number, number>;
  attendance: Record<string, Attendance>;
  scores: Record<string, number>;
  blocks: Block[];
  classes: Class[];
  // ... other fields
}

// ============================================================
// FORM TYPES
// ============================================================

export interface StudentFormData {
  id?: number;
  code?: string;
  holyName: string;
  fullName: string;
  gender: number;
  birthDate: string;
  address?: string;
  fatherName?: string;
  fatherPhone?: string;
  motherName?: string;
  motherPhone?: string;
}

export interface MemberFormData {
  id?: number;
  holyName: string;
  fullName: string;
  phone: string;
  birthDate?: string;
  role: string;
  title?: string;
  block?: string;
  className?: string;
  status?: string;
}

export interface ProgramFormData {
  id?: number;
  name: string;
  type: 'chiến dịch' | 'sinh hoạt';
  date: string;
  startTime: string;
  endTime: string;
  status: 'kích hoạt' | 'tắt';
  countForAttendance: boolean;
}

// ============================================================
// ALPINE.JS STORE TYPES
// ============================================================

export interface TnttAppState {
  // Current user
  currentModule: string;
  user: Member | null;

  // Data
  students: Student[];
  programs: Program[];
  blocks: Block[];
  classes: Class[];

  // UI State
  showProfileForm: boolean;
  showChangePw: boolean;
  pwBusy: boolean;

  // Methods
  changeModule(module: string): void;
  openProfileForm(): void;
  saveProfile(): Promise<void>;
  logout(): void;
}

export interface ToastStore {
  show(message: string, type?: 'success' | 'error' | 'info'): void;
}

export interface LibViewerStore {
  open: boolean;
  item: LibraryItem | null;
}

export interface LibraryItem {
  id: number;
  title: string;
  type: 'article' | 'file';
  categoryName: string;
  body?: string;
  ext?: string;
  sizeKb: number;
  fileUrl?: string;
  viewable: boolean;
}

// ============================================================
// CONFIG TYPES
// ============================================================

export interface AppConfig {
  db: {
    host: string;
    port: number;
    name: string;
    user: string;
    pass: string;
  };
  cutoffMinutes: number;
  passScore: number;
  passAttendance: number;
  defaultPassword: string;
  production: boolean;
}

// ============================================================
// UTILITY TYPES
// ============================================================

export type Scope = 'toàn đoàn' | 'khối' | 'lớp';

export type Permission = 'none' | 'view' | 'edit';

export type RoleCode =
  | 'admin'
  | 'bdh'
  | 'truong_khoi'
  | 'glv_chu_nhiem'
  | 'glv'
  | 'du_bi'
  | 'demo';

export type Gender = 0 | 1; // 0: Nam, 1: Nữ

export type AttendanceStatus = 'C' | 'M' | 'V' | 'P' | null;

// ============================================================
// QR TYPES
// ============================================================

export interface QRData {
  studentId: number;
  programId: number;
  timestamp: number;
}

export interface QRResult {
  id: number;
  ten: string;
  lop: string;
  luc: string;
}
