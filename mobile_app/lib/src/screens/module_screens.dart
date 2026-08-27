import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:qr_flutter/qr_flutter.dart';
import '../core/api_client.dart';

String _friendlyError(Object error) {
  if (error is SocketException || error is http.ClientException || error is TimeoutException) {
    return 'Internet connection or C-Net Library server could not be reached. Please check your mobile data/Wi-Fi and try again.';
  }
  final message = error.toString().replaceFirst('Exception: ', '');
  return message.isEmpty ? 'Unable to load this information. Please try again.' : message;
}

class JsonListScreen extends StatefulWidget {
  const JsonListScreen({super.key, required this.title, required this.api, required this.path, required this.itemBuilder});
  final String title;
  final ApiClient api;
  final String path;
  final Widget Function(BuildContext context, Map<String, dynamic> item) itemBuilder;

  @override
  State<JsonListScreen> createState() => _JsonListScreenState();
}

class _JsonListScreenState extends State<JsonListScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() { super.initState(); _future = widget.api.get(widget.path); }

  Future<void> _refresh() async {
    setState(() => _future = widget.api.get(widget.path));
    await _future;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
        if (snapshot.hasError) return _ErrorView(message: _friendlyError(snapshot.error!), retry: _refresh);
        final root = snapshot.data ?? {};
        final raw = root['data'];
        final items = raw is List ? raw : const [];
        if (items.isEmpty) {
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                const SizedBox(height: 150),
                Icon(Icons.inbox_outlined, size: 58, color: Theme.of(context).colorScheme.outline),
                const SizedBox(height: 12),
                Text('No ${widget.title.toLowerCase()} found.', textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 6),
                const Text('Pull down to refresh.', textAlign: TextAlign.center),
              ],
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(12),
            itemCount: items.length,
            separatorBuilder: (_, __) => const SizedBox(height: 6),
            itemBuilder: (context, index) => Card(child: widget.itemBuilder(context, Map<String, dynamic>.from(items[index] as Map))),
          ),
        );
      },
    ),
  );
}

class DetailScreen extends StatefulWidget {
  const DetailScreen({super.key, required this.title, required this.api, required this.path});
  final String title;
  final ApiClient api;
  final String path;
  @override
  State<DetailScreen> createState() => _DetailScreenState();
}

class _DetailScreenState extends State<DetailScreen> {
  late Future<Map<String, dynamic>> _future;
  @override
  void initState() { super.initState(); _future = widget.api.get(widget.path); }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
        if (snapshot.hasError) return _ErrorView(message: _friendlyError(snapshot.error!), retry: () => setState(() => _future = widget.api.get(widget.path)));
        final root = snapshot.data ?? {};
        final data = root['data'] is Map ? Map<String, dynamic>.from(root['data'] as Map) : root;
        if (root.containsKey('data') && root['data'] == null) return const Center(child: Text('No active record found.'));
        return ListView(
          padding: const EdgeInsets.all(16),
          children: data.entries.where((e) => e.value == null || e.value is! List).map((entry) {
            final value = entry.value is Map ? (entry.value as Map).entries.map((e) => '${e.key}: ${e.value ?? '—'}').join('\n') : '${entry.value ?? '—'}';
            return Card(child: ListTile(title: Text(_label(entry.key)), subtitle: Text(value)));
          }).toList(),
        );
      },
    ),
  );
}

class SeatScreen extends StatefulWidget {
  const SeatScreen({super.key, required this.api});
  final ApiClient api;
  @override
  State<SeatScreen> createState() => _SeatScreenState();
}

class _SeatScreenState extends State<SeatScreen> {
  late Future<Map<String, dynamic>> _future;
  @override
  void initState() { super.initState(); _future = widget.api.get('/seat-allocation'); }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Seat / Study Slot')),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
        if (snapshot.hasError) return _ErrorView(message: _friendlyError(snapshot.error!), retry: () => setState(() => _future = widget.api.get('/seat-allocation')));
        final data = Map<String, dynamic>.from((snapshot.data?['data'] as Map?) ?? {});
        final active = data['active'] as Map?;
        final history = data['history'] as List? ?? const [];
        return ListView(padding: const EdgeInsets.all(16), children: [
          Text('Current Allocation', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          Card(child: Padding(padding: const EdgeInsets.all(16), child: Text(active == null ? 'No active seat allocation.' : _mapSummary(active)))),
          const SizedBox(height: 20),
          Text('History', style: Theme.of(context).textTheme.titleLarge),
          if (history.isEmpty) const Card(child: ListTile(title: Text('No allocation history found.'))),
          ...history.map((e) => Card(child: ListTile(title: Text((e as Map)['status']?.toString() ?? 'Allocation'), subtitle: Text(_mapSummary(e))))),
        ]);
      },
    ),
  );
}

class QrMemberScreen extends StatefulWidget {
  const QrMemberScreen({super.key, required this.api});
  final ApiClient api;
  @override
  State<QrMemberScreen> createState() => _QrMemberScreenState();
}

class _QrMemberScreenState extends State<QrMemberScreen> {
  late Future<Map<String, dynamic>> _future;
  @override
  void initState() { super.initState(); _future = widget.api.get('/qr-member-id'); }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('QR Member ID')),
    body: FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
        if (snapshot.hasError) return _ErrorView(message: _friendlyError(snapshot.error!), retry: () => setState(() => _future = widget.api.get('/qr-member-id')));
        final data = snapshot.data ?? {};
        final token = data['qr_token']?.toString() ?? '';
        final attendanceUrl = data['attendance_url']?.toString() ?? '';
        final qrValue = attendanceUrl.isNotEmpty ? attendanceUrl : token;
        return ListView(padding: const EdgeInsets.all(24), children: [
          Text(data['name']?.toString() ?? 'Student', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
          Text(data['student_code']?.toString() ?? '—', textAlign: TextAlign.center),
          const SizedBox(height: 24),
          if (qrValue.isNotEmpty)
            Center(
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: QrImageView(data: qrValue, version: QrVersions.auto, size: 230),
                ),
              ),
            )
          else
            const Card(child: ListTile(leading: Icon(Icons.qr_code_2), title: Text('QR member ID is not available yet.'))),
          const SizedBox(height: 18),
          if (token.isNotEmpty) SelectableText('Member Token: $token'),
          if (attendanceUrl.isNotEmpty) ...[
            const SizedBox(height: 10),
            SelectableText('Attendance URL: $attendanceUrl'),
          ],
          const SizedBox(height: 14),
          const Text('Present this QR member ID for supported C-Net Library attendance and member operations.', textAlign: TextAlign.center),
        ]);
      },
    ),
  );
}

class SupportScreen extends StatefulWidget {
  const SupportScreen({super.key, required this.api});
  final ApiClient api;
  @override
  State<SupportScreen> createState() => _SupportScreenState();
}

class _SupportScreenState extends State<SupportScreen> {
  final _controller = TextEditingController();
  bool _sending = false;

  Future<void> _submit() async {
    final message = _controller.text.trim();
    if (message.isEmpty) return;
    setState(() => _sending = true);
    try {
      final response = await widget.api.post('/support', body: {'message': message}, authenticated: true);
      if (!mounted) return;
      _controller.clear();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${response['message'] ?? 'Submitted'} ${response['enquiry_no'] ?? ''}')));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_friendlyError(e))));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Support / Enquiry')),
    body: ListView(padding: const EdgeInsets.all(16), children: [
      const Card(child: ListTile(leading: Icon(Icons.support_agent_outlined), title: Text('Need help?'), subtitle: Text('Send your message to C-Net Library management.'))),
      const SizedBox(height: 12),
      TextField(controller: _controller, maxLines: 7, maxLength: 2000, decoration: const InputDecoration(labelText: 'Message', border: OutlineInputBorder())),
      const SizedBox(height: 12),
      FilledButton.icon(onPressed: _sending ? null : _submit, icon: const Icon(Icons.send), label: Text(_sending ? 'Sending...' : 'Submit')),
    ]),
  );

  @override
  void dispose() { _controller.dispose(); super.dispose(); }
}

String _label(String key) => key.replaceAll('_', ' ').split(' ').map((e) => e.isEmpty ? e : '${e[0].toUpperCase()}${e.substring(1)}').join(' ');
String _mapSummary(Map map) => map.entries.where((e) => e.value != null && e.value is! Map && e.value is! List).map((e) => '${_label(e.key.toString())}: ${e.value}').join('\n');

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.retry});
  final String message;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(Icons.cloud_off_outlined, size: 52, color: Theme.of(context).colorScheme.error),
        const SizedBox(height: 12),
        Text(message, textAlign: TextAlign.center),
        const SizedBox(height: 12),
        FilledButton.icon(onPressed: retry, icon: const Icon(Icons.refresh), label: const Text('Retry')),
      ]),
    ),
  );
}
