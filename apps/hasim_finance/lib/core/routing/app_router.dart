import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/routing/finance_shell.dart';
import 'package:hasim_finance/features/auth/forgot_password_screen.dart';
import 'package:hasim_finance/features/auth/login_screen.dart';
import 'package:hasim_finance/features/auth/workspace_gate_screens.dart';
import 'package:hasim_finance/features/customers/customers_screens.dart';
import 'package:hasim_finance/features/dashboard/dashboard_screen.dart';
import 'package:hasim_finance/features/invoices/invoices_screens.dart';
import 'package:hasim_finance/features/modules/module_screens.dart';
import 'package:hasim_finance/features/quotes/quotes_screens.dart';

const _publicAuth = {'/login', '/forgot-password', '/reset-password'};

final appRouterProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier<int>(0);
  ref.onDispose(refresh.dispose);
  ref.listen<AuthState>(authControllerProvider, (prev, next) {
    if (prev?.isAuthenticated != next.isAuthenticated ||
        prev?.isLoading != next.isLoading ||
        prev?.financeEnabled != next.financeEnabled ||
        prev?.needsWorkspaceSelection != next.needsWorkspaceSelection ||
        prev?.workspace?.id != next.workspace?.id) {
      refresh.value++;
    }
  });

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: refresh,
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      final loc = state.matchedLocation;
      if (auth.isLoading) {
        return loc == '/splash' ? null : '/splash';
      }
      if (!auth.isAuthenticated) {
        if (_publicAuth.contains(loc)) return null;
        return '/login';
      }
      if (auth.needsWorkspaceSelection) {
        return loc == '/workspaces' ? null : '/workspaces';
      }
      if (!auth.financeEnabled) {
        return loc == '/finance-unavailable' ? null : '/finance-unavailable';
      }
      if (loc == '/login' ||
          loc == '/splash' ||
          loc == '/forgot-password' ||
          loc == '/reset-password' ||
          loc == '/workspaces' ||
          loc == '/finance-unavailable') {
        return '/dashboard';
      }
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const SplashScreen()),
      GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
      GoRoute(path: '/forgot-password', builder: (_, _) => const ForgotPasswordScreen()),
      GoRoute(path: '/reset-password', builder: (_, _) => const ResetPasswordScreen()),
      GoRoute(path: '/workspaces', builder: (_, _) => const WorkspaceSelectScreen()),
      GoRoute(path: '/finance-unavailable', builder: (_, _) => const FinanceUnavailableScreen()),
      ShellRoute(
        builder: (context, state, child) => FinanceShell(child: child),
        routes: [
          GoRoute(path: '/dashboard', builder: (_, _) => const DashboardScreen()),
          GoRoute(path: '/search', builder: (_, _) => const FinanceSearchScreen()),
          GoRoute(path: '/customers', builder: (_, _) => const CustomersScreen()),
          GoRoute(path: '/customers/new', builder: (_, _) => const CustomerFormScreen()),
          GoRoute(
            path: '/customers/:id',
            builder: (_, state) => CustomerDetailScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(
            path: '/customers/:id/edit',
            builder: (_, state) => CustomerFormScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(path: '/quotes', builder: (_, _) => const QuotesScreen()),
          GoRoute(path: '/quotes/new', builder: (_, _) => const QuoteFormScreen()),
          GoRoute(
            path: '/quotes/:id',
            builder: (_, state) => QuoteDetailScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(
            path: '/quotes/:id/edit',
            builder: (_, state) => QuoteFormScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(path: '/invoices', builder: (_, _) => const InvoicesScreen()),
          GoRoute(path: '/invoices/new', builder: (_, _) => const InvoiceFormScreen()),
          GoRoute(
            path: '/invoices/:id',
            builder: (_, state) => InvoiceDetailScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(
            path: '/invoices/:id/edit',
            builder: (_, state) => InvoiceFormScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(path: '/payments', builder: (_, _) => const PaymentsScreen()),
          GoRoute(
            path: '/payments/:id',
            builder: (_, state) => PaymentDetailScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(path: '/receipts', builder: (_, _) => const ReceiptsScreen()),
          GoRoute(
            path: '/receipts/:id',
            builder: (_, state) => ReceiptDetailScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(
            path: '/statements',
            builder: (_, state) {
              final raw = state.uri.queryParameters['customer_id'];
              return StatementScreen(customerId: int.tryParse(raw ?? ''));
            },
          ),
          GoRoute(path: '/notes', builder: (_, _) => const NotesScreen()),
          GoRoute(path: '/notes/new', builder: (_, _) => const NoteFormScreen()),
          GoRoute(
            path: '/notes/:id',
            builder: (_, state) => NoteDetailScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(path: '/contracts', builder: (_, _) => const ContractsScreen()),
          GoRoute(path: '/contracts/new', builder: (_, _) => const ContractFormScreen()),
          GoRoute(
            path: '/contracts/:id',
            builder: (_, state) => ContractDetailScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(path: '/expenses', builder: (_, _) => const ExpensesScreen()),
          GoRoute(path: '/expenses/new', builder: (_, _) => const ExpenseFormScreen()),
          GoRoute(
            path: '/expenses/:id',
            builder: (_, state) => ExpenseDetailScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(path: '/purchases', builder: (_, _) => const PurchasesScreen()),
          GoRoute(path: '/purchases/new', builder: (_, _) => const PurchaseFormScreen()),
          GoRoute(
            path: '/purchases/:id',
            builder: (_, state) => PurchaseDetailScreen(id: int.parse(state.pathParameters['id']!)),
          ),
          GoRoute(path: '/reports', builder: (_, _) => const ReportsScreen()),
          GoRoute(path: '/settings', builder: (_, _) => const SettingsScreen()),
        ],
      ),
    ],
  );
});
