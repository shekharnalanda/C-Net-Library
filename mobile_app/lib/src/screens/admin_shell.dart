import 'package:flutter/material.dart';
import '../core/api_client.dart';
import '../core/token_store.dart';
import 'module_screens.dart';

class AdminShell extends StatefulWidget {
  const AdminShell({
    super.key,
    required this.api,
    required this.tokenStore,
    required this.onSignedOut,
  });

  final ApiClient api;
  final TokenStore tokenStore;
  final VoidCallback onSignedOut;

  @override
  State<AdminShell> createState() => _AdminShellState();
}

class _AdminShellState extends State<AdminShell> {
  late Future<Map<String, dynamic>> _dashboard;

  @override
  void initState() {
    super.initState();
    _dashboard = widget.api.get('/admin/dashboard');
  }

  Future<void> _logout() async {
    try {
      await widget.api.post('/admin/logout', authenticated: true);
    } catch (_) {}
    await widget.tokenStore.clear();
    if (mounted) widget.onSignedOut();
  }

  void _open(String title, String path) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => JsonListScreen(
          title: title,
          api: widget.api,
          path: path,
          itemBuilder: (_, item) => ListTile(
            title: Text(
              item['name']?.toString() ??
                  item['title']?.toString() ??
                  item['student_code']?.toString() ??
                  item['locker_no']?.toString() ??
                  title,
            ),
            subtitle: Text(
              [
                item['mobile'],
                item['email'],
                item['status'],
                item['payment_status'],
              ].where((e) => e != null && e.toString().isNotEmpty).join(' • '),
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('C-Net Library Admin'),
        actions: [
          IconButton(onPressed: _logout, icon: const Icon(Icons.logout), tooltip: 'Logout'),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _dashboard,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(snapshot.error.toString(), textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: () => setState(() => _dashboard = widget.api.get('/admin/dashboard')),
                      child: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            );
          }

          final data = snapshot.data ?? {};
          final admin = (data['admin'] as Map?)?.cast<String, dynamic>() ?? {};
          final counts = (data['counts'] as Map?)?.cast<String, dynamic>() ?? {};
          final finance = (data['finance'] as Map?)?.cast<String, dynamic>() ?? {};

          return RefreshIndicator(
            onRefresh: () async => setState(() => _dashboard = widget.api.get('/admin/dashboard')),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  'Welcome, ${admin['name'] ?? 'Administrator'}',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 4),
                Text('${admin['role'] ?? ''}${admin['global_admin'] == true ? ' • Global Access' : ''}'),
                const SizedBox(height: 18),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: [
                    _Metric('Students', counts['students'] ?? 0, Icons.people_outline),
                    _Metric('Active', counts['active_students'] ?? 0, Icons.verified_user_outlined),
                    _Metric('Enquiries', counts['enquiries'] ?? 0, Icons.forum_outlined),
                    _Metric('Today Attendance', counts['today_attendance'] ?? 0, Icons.fact_check_outlined),
                    _Metric('Book Copies', counts['book_copies'] ?? 0, Icons.menu_book_outlined),
                    _Metric('Available Books', counts['available_book_copies'] ?? 0, Icons.library_books_outlined),
                    _Metric('Book Issues', counts['active_book_issues'] ?? 0, Icons.assignment_outlined),
                    _Metric('Locker Allocations', counts['active_locker_allocations'] ?? 0, Icons.lock_outline),
                  ],
                ),
                const SizedBox(height: 18),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      children: [
                        const Icon(Icons.currency_rupee),
                        const SizedBox(width: 12),
                        Expanded(child: Text('Today Collection\n₹${finance['today_collection'] ?? 0}')),
                        Expanded(child: Text('Month Collection\n₹${finance['month_collection'] ?? 0}')),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Text('Management', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 8),
                _AdminTile('Students', Icons.people_outline, () => _open('Students', '/admin/students')),
                _AdminTile('Enquiries', Icons.forum_outlined, () => _open('Enquiries', '/admin/enquiries')),
                _AdminTile('Payments', Icons.payments_outlined, () => _open('Payments', '/admin/payments')),
                _AdminTile('Attendance', Icons.fact_check_outlined, () => _open('Attendance', '/admin/attendance')),
                _AdminTile('Books', Icons.menu_book_outlined, () => _open('Books', '/admin/books')),
                _AdminTile('Book Issues', Icons.assignment_return_outlined, () => _open('Book Issues', '/admin/book-issues')),
                _AdminTile('Lockers', Icons.lock_outline, () => _open('Lockers', '/admin/lockers')),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric(this.label, this.value, this.icon);
  final String label;
  final dynamic value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 160,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon),
              const SizedBox(height: 10),
              Text('$value', style: Theme.of(context).textTheme.titleLarge),
              Text(label),
            ],
          ),
        ),
      ),
    );
  }
}

class _AdminTile extends StatelessWidget {
  const _AdminTile(this.title, this.icon, this.onTap);
  final String title;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Icon(icon),
        title: Text(title),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}
