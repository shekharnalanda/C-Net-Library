import 'package:flutter/material.dart';
import '../core/api_client.dart';
import '../core/token_store.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.api, required this.tokenStore, required this.onSignedIn});
  final ApiClient api;
  final TokenStore tokenStore;
  final VoidCallback onSignedIn;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _loading = false;
  String? _error;

  Future<void> _login() async {
    setState(() { _loading = true; _error = null; });
    try {
      final data = await widget.api.post('/login', body: {
        'email': _email.text.trim(),
        'password': _password.text,
        'device_name': 'C-Net Library App',
      });
      await widget.tokenStore.write(data['token'] as String);
      if (mounted) widget.onSignedIn();
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Icon(Icons.local_library_rounded, size: 72),
                  const SizedBox(height: 12),
                  const Text('C-Net Library', textAlign: TextAlign.center, style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 6),
                  const Text('Student Login', textAlign: TextAlign.center),
                  const SizedBox(height: 28),
                  TextField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder())),
                  const SizedBox(height: 14),
                  TextField(controller: _password, obscureText: true, decoration: const InputDecoration(labelText: 'Password', border: OutlineInputBorder())),
                  if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
                  const SizedBox(height: 18),
                  FilledButton.icon(onPressed: _loading ? null : _login, icon: const Icon(Icons.login), label: Text(_loading ? 'Signing in...' : 'Login')),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
