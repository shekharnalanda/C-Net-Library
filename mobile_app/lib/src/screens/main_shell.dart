import 'package:flutter/material.dart';
import '../core/api_client.dart';
import '../core/token_store.dart';

class MainShell extends StatefulWidget {
  const MainShell({super.key, required this.api, required this.tokenStore, required this.onSignedOut});
  final ApiClient api;
  final TokenStore tokenStore;
  final VoidCallback onSignedOut;

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final pages = [
      DashboardScreen(api: widget.api),
      LibraryHub(api: widget.api),
      ActivityHub(api: widget.api),
      ProfileScreen(api: widget.api, tokenStore: widget.tokenStore, onSignedOut: widget.onSignedOut),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.menu_book_outlined), selectedIcon: Icon(Icons.menu_book), label: 'Library'),
          NavigationDestination(icon: Icon(Icons.timeline_outlined), selectedIcon: Icon(Icons.timeline), label: 'Activity'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Profile'),
        ],
      ),
    );
  }
}

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.api});
  final ApiClient api;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late Future<Map<String, dynamic>> _data;

  @override
  void initState() { super.initState(); _data = widget.api.get('/dashboard'); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('C-Net Library')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _data,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
          if (snapshot.hasError) return _ErrorView(message: snapshot.error.toString(), retry: () => setState(() => _data = widget.api.get('/dashboard')));
          final data = snapshot.data ?? {};
          final student = (data['student'] as Map?)?.cast<String, dynamic>() ?? {};
          final membership = (data['membership'] as Map?)?.cast<String, dynamic>() ?? {};
          final finance = (data['finance'] as Map?)?.cast<String, dynamic>() ?? {};
          return RefreshIndicator(
            onRefresh: () async => setState(() => _data = widget.api.get('/dashboard')),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text('Welcome, ${student['name'] ?? 'Student'}', style: Theme.of(context).textTheme.headlineSmall),
                const SizedBox(height: 16),
                Wrap(spacing: 12, runSpacing: 12, children: [
                  _SummaryCard(title: 'Membership', value: membership['status']?.toString() ?? '—', icon: Icons.card_membership),
                  _SummaryCard(title: 'Fee Due', value: '₹${finance['due'] ?? 0}', icon: Icons.payments_outlined),
                  _SummaryCard(title: 'Paid', value: '₹${finance['paid'] ?? 0}', icon: Icons.check_circle_outline),
                  _SummaryCard(title: 'Study Minutes', value: '${data['study_minutes'] ?? 0}', icon: Icons.schedule),
                ]),
              ],
            ),
          );
        },
      ),
    );
  }
}

class LibraryHub extends StatelessWidget {
  const LibraryHub({super.key, required this.api});
  final ApiClient api;
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: const Text('Library')), body: ListView(padding: const EdgeInsets.all(16), children: const [
    ListTile(leading: Icon(Icons.search), title: Text('Books'), subtitle: Text('Search library catalogue')),
    ListTile(leading: Icon(Icons.assignment_returned), title: Text('Issued Books'), subtitle: Text('View issued and due books')),
    ListTile(leading: Icon(Icons.cloud_download_outlined), title: Text('Digital Library'), subtitle: Text('Access digital resources')),
    ListTile(leading: Icon(Icons.work_outline), title: Text('Jobs'), subtitle: Text('View current opportunities')),
  ]));
}

class ActivityHub extends StatelessWidget {
  const ActivityHub({super.key, required this.api});
  final ApiClient api;
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: const Text('Activity')), body: ListView(padding: const EdgeInsets.all(16), children: const [
    ListTile(leading: Icon(Icons.badge_outlined), title: Text('Membership')),
    ListTile(leading: Icon(Icons.payments_outlined), title: Text('Payments')),
    ListTile(leading: Icon(Icons.fact_check_outlined), title: Text('Attendance')),
    ListTile(leading: Icon(Icons.event_seat_outlined), title: Text('Seat / Study Slot')),
    ListTile(leading: Icon(Icons.qr_code_2), title: Text('QR Member ID')),
  ]));
}

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key, required this.api, required this.tokenStore, required this.onSignedOut});
  final ApiClient api;
  final TokenStore tokenStore;
  final VoidCallback onSignedOut;
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late Future<Map<String, dynamic>> _profile;
  @override
  void initState() { super.initState(); _profile = widget.api.get('/profile'); }

  Future<void> _logout() async {
    try { await widget.api.post('/logout', authenticated: true); } catch (_) {}
    await widget.tokenStore.clear();
    if (mounted) widget.onSignedOut();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Profile')),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _profile,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
        if (snapshot.hasError) return _ErrorView(message: snapshot.error.toString(), retry: () => setState(() => _profile = widget.api.get('/profile')));
        final data = snapshot.data ?? {};
        final student = (data['student'] as Map?)?.cast<String, dynamic>() ?? data;
        return ListView(padding: const EdgeInsets.all(16), children: [
          const CircleAvatar(radius: 42, child: Icon(Icons.person, size: 42)),
          const SizedBox(height: 12),
          Text(student['name']?.toString() ?? 'Student', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 20),
          ListTile(leading: const Icon(Icons.badge_outlined), title: const Text('Student Code'), subtitle: Text(student['student_code']?.toString() ?? '—')),
          ListTile(leading: const Icon(Icons.email_outlined), title: const Text('Email'), subtitle: Text(student['email']?.toString() ?? '—')),
          ListTile(leading: const Icon(Icons.phone_outlined), title: const Text('Mobile'), subtitle: Text(student['mobile']?.toString() ?? '—')),
          const SizedBox(height: 16),
          OutlinedButton.icon(onPressed: _logout, icon: const Icon(Icons.logout), label: const Text('Logout')),
        ]);
      },
    ),
  );
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.title, required this.value, required this.icon});
  final String title; final String value; final IconData icon;
  @override
  Widget build(BuildContext context) => SizedBox(width: 165, child: Card(child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon), const SizedBox(height: 10), Text(value, style: Theme.of(context).textTheme.titleLarge), Text(title)]))));
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.retry});
  final String message; final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [Text(message, textAlign: TextAlign.center), const SizedBox(height: 12), FilledButton(onPressed: retry, child: const Text('Retry'))])));
}
