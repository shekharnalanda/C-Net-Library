import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../core/api_client.dart';
import '../core/token_store.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    super.key,
    required this.api,
    required this.tokenStore,
    required this.role,
    required this.onSignedIn,
  });

  final ApiClient api;
  final TokenStore tokenStore;
  final String role;
  final VoidCallback onSignedIn;

  bool get isAdmin => role == 'admin';

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _loading = false;
  bool _showPassword = false;
  String? _error;

  String _friendlyError(Object error) {
    if (error is SocketException || error is http.ClientException || error is TimeoutException) {
      return 'Internet connection or C-Net Library server could not be reached. Please check your mobile data/Wi-Fi and try again.';
    }
    final message = error.toString().replaceFirst('Exception: ', '');
    return message.isEmpty ? 'Login could not be completed. Please try again.' : message;
  }

  Future<void> _login() async {
    if (_email.text.trim().isEmpty || _password.text.isEmpty) {
      setState(() => _error = 'Email and password are required.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await widget.api.post(
        widget.isAdmin ? '/admin/login' : '/login',
        body: {
          'email': _email.text.trim(),
          'password': _password.text,
          'device_name': widget.isAdmin ? 'C-Net Library Admin App' : 'C-Net Library Student App',
        },
      );
      final token = data['token']?.toString();
      if (token == null || token.isEmpty) throw Exception('Login token was not returned.');
      await widget.tokenStore.write(token, role: widget.role);
      if (!mounted) return;
      Navigator.of(context).pop();
      widget.onSignedIn();
    } catch (e) {
      if (mounted) setState(() => _error = _friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.isAdmin ? 'Admin / Staff Login' : 'Student Login';
    final icon = widget.isAdmin ? Icons.admin_panel_settings_outlined : Icons.school_outlined;

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Icon(icon, size: 72),
                  const SizedBox(height: 12),
                  const Text(
                    'C-Net Library',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 6),
                  Text(title, textAlign: TextAlign.center),
                  const SizedBox(height: 28),
                  TextField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(labelText: 'Email'),
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: _password,
                    obscureText: !_showPassword,
                    onSubmitted: (_) => _loading ? null : _login(),
                    decoration: InputDecoration(
                      labelText: 'Password',
                      suffixIcon: IconButton(
                        onPressed: () => setState(() => _showPassword = !_showPassword),
                        icon: Icon(_showPassword ? Icons.visibility_off : Icons.visibility),
                      ),
                    ),
                  ),
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                    ),
                  const SizedBox(height: 18),
                  FilledButton.icon(
                    onPressed: _loading ? null : _login,
                    icon: const Icon(Icons.login),
                    label: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(_loading ? 'Signing in...' : 'Login'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextButton(
                    onPressed: _loading ? null : () => Navigator.of(context).pop(),
                    child: const Text('Back to Home'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }
}
