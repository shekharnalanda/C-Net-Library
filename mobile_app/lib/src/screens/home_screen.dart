import 'package:flutter/material.dart';
import '../core/api_client.dart';
import '../core/token_store.dart';
import 'login_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({
    super.key,
    required this.api,
    required this.tokenStore,
    required this.onSignedIn,
  });

  final ApiClient api;
  final TokenStore tokenStore;
  final VoidCallback onSignedIn;

  void _openLogin(BuildContext context, String role) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => LoginScreen(
          api: api,
          tokenStore: tokenStore,
          role: role,
          onSignedIn: onSignedIn,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 28, 20, 24),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const CircleAvatar(
                        radius: 54,
                        child: Icon(Icons.local_library_rounded, size: 62),
                      ),
                      const SizedBox(height: 18),
                      Text(
                        'C-Net Library',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Study • Library • Digital Learning',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.titleMedium,
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Membership, study seats, books, digital resources, attendance and library services in one place.',
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 28),
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text('Login', style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                              const SizedBox(height: 6),
                              const Text('Choose how you want to continue.'),
                              const SizedBox(height: 18),
                              FilledButton.icon(
                                onPressed: () => _openLogin(context, 'student'),
                                icon: const Icon(Icons.school_outlined),
                                label: const Padding(
                                  padding: EdgeInsets.symmetric(vertical: 13),
                                  child: Text('Student Login'),
                                ),
                              ),
                              const SizedBox(height: 12),
                              OutlinedButton.icon(
                                onPressed: () => _openLogin(context, 'admin'),
                                icon: const Icon(Icons.admin_panel_settings_outlined),
                                label: const Padding(
                                  padding: EdgeInsets.symmetric(vertical: 13),
                                  child: Text('Admin / Staff Login'),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 22),
                      Wrap(
                        alignment: WrapAlignment.center,
                        spacing: 18,
                        runSpacing: 10,
                        children: const [
                          _Feature(icon: Icons.event_seat_outlined, label: 'Study Seats'),
                          _Feature(icon: Icons.menu_book_outlined, label: 'Books'),
                          _Feature(icon: Icons.cloud_outlined, label: 'Digital Library'),
                          _Feature(icon: Icons.qr_code_2, label: 'QR Member ID'),
                        ],
                      ),
                      const SizedBox(height: 28),
                      const Text(
                        'Powered by MCI Educational Group',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _Feature extends StatelessWidget {
  const _Feature({required this.icon, required this.label});
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(fontSize: 12)),
      ],
    );
  }
}
