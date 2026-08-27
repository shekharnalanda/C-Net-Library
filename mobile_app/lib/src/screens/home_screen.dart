import 'package:flutter/material.dart';
import '../core/api_client.dart';
import '../core/token_store.dart';
import 'login_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key, required this.api, required this.tokenStore, required this.onSignedIn});
  final ApiClient api;
  final TokenStore tokenStore;
  final VoidCallback onSignedIn;

  void _openLogin(BuildContext context, String role) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => LoginScreen(api: api, tokenStore: tokenStore, role: role, onSignedIn: onSignedIn)));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 14,
        title: const Text('C-Net Library', style: TextStyle(fontWeight: FontWeight.w800)),
        actions: [
          IconButton(onPressed: () => _openLogin(context, 'student'), icon: const Icon(Icons.school_outlined), tooltip: 'Student Login'),
          IconButton(onPressed: () => _openLogin(context, 'admin'), icon: const Icon(Icons.admin_panel_settings_outlined), tooltip: 'Admin / Staff Login'),
          const SizedBox(width: 6),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.only(bottom: 28),
          children: [
            _Hero(onStudentLogin: () => _openLogin(context, 'student'), onAdminLogin: () => _openLogin(context, 'admin')),
            _Section(
              title: 'A disciplined space for serious preparation',
              subtitle: 'Quiet study spaces, flexible memberships, books, digital learning, attendance support and career updates—all connected through one C-Net Library platform.',
              child: const Wrap(spacing: 12, runSpacing: 12, children: [
                _InfoChip(Icons.schedule_outlined, 'Flexible study slots'),
                _InfoChip(Icons.how_to_reg_outlined, 'Online admission'),
                _InfoChip(Icons.dashboard_outlined, 'Student portal'),
                _InfoChip(Icons.lock_outline, 'Locker facility'),
              ]),
            ),
            const _Section(
              title: 'Study plans for different routines',
              subtitle: 'Choose the duration and monthly plan that suits your daily preparation. Slot timing can be fixed or flexible according to the available plan.',
              child: _CardGrid(items: [
                _FeatureData(Icons.timer_outlined, 'Hourly Study Slots', 'Multiple hourly durations with fixed or flexible timing.'),
                _FeatureData(Icons.calendar_month_outlined, 'Monthly Plans', 'Membership plans connected with study-slot duration and validity.'),
                _FeatureData(Icons.event_seat_outlined, 'Managed Seats', 'Hall-wise seat inventory and controlled seat allocation.'),
                _FeatureData(Icons.lock_outline, 'Locker Plans', 'Hall-wise locker inventory, allocation and fee tracking.'),
              ]),
            ),
            const _Section(
              title: 'Library & learning services',
              subtitle: 'The physical library, digital resources and student services work together so preparation stays organised.',
              child: _CardGrid(items: [
                _FeatureData(Icons.menu_book_outlined, 'Physical Library', 'Books with managed issue, return, reservation and due-date tracking.'),
                _FeatureData(Icons.cloud_outlined, 'Digital Library', 'Notes, ebooks, papers, videos and study resources for members.'),
                _FeatureData(Icons.fact_check_outlined, 'Attendance', 'QR and attendance records help students track study activity.'),
                _FeatureData(Icons.work_outline, 'Career Support', 'Current job opportunities and useful career information.'),
              ]),
            ),
            const _Section(
              title: 'Our C-Net Library branches',
              subtitle: 'Services are managed branch-wise so halls, seats, lockers, students and reports stay organised.',
              child: Column(children: [
                _BranchCard(name: 'C-Net Library - Main Branch', code: 'CNL-MAIN', description: 'Main C-Net Library study halls, seats, library services and memberships.'),
                SizedBox(height: 12),
                _BranchCard(name: 'MCI Library', code: 'CNL-MCI', description: 'MCI branch with its own halls, seats, lockers and branch-wise student management.'),
              ]),
            ),
            _Section(
              title: 'Membership & admission',
              subtitle: 'New students can submit an admission request, choose a branch and study plan, and receive membership access after approval.',
              child: Card(child: Padding(padding: const EdgeInsets.all(18), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const _Step(number: '1', title: 'Choose branch & plan', text: 'Select the C-Net Library branch, slot duration and suitable monthly plan.'),
                const _Step(number: '2', title: 'Submit admission', text: 'Provide student details for management review.'),
                const _Step(number: '3', title: 'Membership activation', text: 'After approval, use Student Login for dashboard, attendance, payments, seat and library activity.'),
                const SizedBox(height: 14),
                FilledButton.icon(onPressed: () => _openLogin(context, 'student'), icon: const Icon(Icons.login), label: const Padding(padding: EdgeInsets.symmetric(vertical: 12), child: Text('Already a Member? Student Login'))),
              ]))),
            ),
            _Section(
              title: 'Secure management access',
              subtitle: 'Authorised C-Net Library administrators and staff can use the dedicated mobile management area.',
              child: Card(child: Padding(padding: const EdgeInsets.all(18), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Text('Admin mobile access includes students, enquiries, payments, attendance, books, book issues and locker information.'),
                const SizedBox(height: 16),
                OutlinedButton.icon(onPressed: () => _openLogin(context, 'admin'), icon: const Icon(Icons.admin_panel_settings_outlined), label: const Padding(padding: EdgeInsets.symmetric(vertical: 12), child: Text('Admin / Staff Login'))),
              ]))),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 28, 20, 8),
              child: Card(child: Padding(padding: const EdgeInsets.all(22), child: Column(children: [
                Image.asset('assets/cnet-library-logo.png', height: 82, fit: BoxFit.contain),
                const SizedBox(height: 12),
                Text('C-Net Library', style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                const Text('READ • LEARN • GROW', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 6),
                const Text('Study Hall • Physical Library • Digital Learning • Student Services', textAlign: TextAlign.center),
                const SizedBox(height: 8),
                const Text('Powered by MCI Educational Group', style: TextStyle(fontSize: 12)),
              ]))),
            ),
          ],
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.onStudentLogin, required this.onAdminLogin});
  final VoidCallback onStudentLogin;
  final VoidCallback onAdminLogin;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(borderRadius: BorderRadius.circular(24), gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [theme.colorScheme.primaryContainer, theme.colorScheme.surfaceContainerHighest])),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Center(child: Image.asset('assets/cnet-library-logo.png', height: 112, fit: BoxFit.contain)),
        const SizedBox(height: 16),
        Center(child: Text('C-Net Library', textAlign: TextAlign.center, style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w900))),
        const SizedBox(height: 4),
        const Center(child: Text('READ • LEARN • GROW', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: 1.2))),
        const SizedBox(height: 18),
        Text('Study with focus. Build a stronger routine.', style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
        const SizedBox(height: 10),
        const Text('C-Net Library brings focused study halls, flexible plans, physical and digital library services, attendance, lockers and student support into one organised system.'),
        const SizedBox(height: 20),
        Row(children: [
          Expanded(child: FilledButton.icon(onPressed: onStudentLogin, icon: const Icon(Icons.school_outlined), label: const Text('Student Login'))),
          const SizedBox(width: 10),
          Expanded(child: OutlinedButton.icon(onPressed: onAdminLogin, icon: const Icon(Icons.admin_panel_settings_outlined), label: const Text('Admin / Staff'))),
        ]),
      ]),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.subtitle, required this.child});
  final String title; final String subtitle; final Widget child;
  @override Widget build(BuildContext context) => Padding(padding: const EdgeInsets.fromLTRB(20, 28, 20, 0), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)), const SizedBox(height: 6), Text(subtitle, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)), const SizedBox(height: 16), child]));
}
class _InfoChip extends StatelessWidget { const _InfoChip(this.icon, this.label); final IconData icon; final String label; @override Widget build(BuildContext context) => Chip(avatar: Icon(icon, size: 18), label: Text(label)); }
class _FeatureData { const _FeatureData(this.icon, this.title, this.text); final IconData icon; final String title; final String text; }
class _CardGrid extends StatelessWidget { const _CardGrid({required this.items}); final List<_FeatureData> items; @override Widget build(BuildContext context) => LayoutBuilder(builder: (context, constraints) { final twoColumns = constraints.maxWidth >= 620; final width = twoColumns ? (constraints.maxWidth - 12) / 2 : constraints.maxWidth; return Wrap(spacing: 12, runSpacing: 12, children: items.map((item) => SizedBox(width: width, child: Card(child: Padding(padding: const EdgeInsets.all(18), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(item.icon, size: 30), const SizedBox(height: 10), Text(item.title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)), const SizedBox(height: 5), Text(item.text)]))))).toList()); }); }
class _BranchCard extends StatelessWidget { const _BranchCard({required this.name, required this.code, required this.description}); final String name; final String code; final String description; @override Widget build(BuildContext context) => Card(child: ListTile(leading: const CircleAvatar(child: Icon(Icons.apartment_outlined)), title: Text(name), subtitle: Text('$code\n$description'), isThreeLine: true)); }
class _Step extends StatelessWidget { const _Step({required this.number, required this.title, required this.text}); final String number; final String title; final String text; @override Widget build(BuildContext context) => Padding(padding: const EdgeInsets.only(bottom: 14), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [CircleAvatar(radius: 16, child: Text(number)), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(fontWeight: FontWeight.w700)), const SizedBox(height: 2), Text(text)]))])); }
