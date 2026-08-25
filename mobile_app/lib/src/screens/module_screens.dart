import 'package:flutter/material.dart';
import '../core/api_client.dart';

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
  void initState() {
    super.initState();
    _future = widget.api.get(widget.path);
  }

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
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return _ErrorView(message: snapshot.error.toString(), retry: _refresh);
            }
            final root = snapshot.data ?? {};
            final raw = root['data'];
            final items = raw is List ? raw : const [];
            if (items.isEmpty) {
              return RefreshIndicator(
                onRefresh: _refresh,
                child: ListView(children: const [SizedBox(height: 180), Center(child: Text('No records found.'))]),
              );
            }
            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView.separated(
                padding: const EdgeInsets.all(12),
                itemCount: items.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (context, index) => widget.itemBuilder(context, Map<String, dynamic>.from(items[index] as Map)),
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
  void initState() {
    super.initState();
    _future = widget.api.get(widget.path);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(widget.title)),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            if (snapshot.hasError) return _ErrorView(message: snapshot.error.toString(), retry: () => setState(() => _future = widget.api.get(widget.path)));
            final root = snapshot.data ?? {};
            final data = root['data'] is Map ? Map<String, dynamic>.from(root['data'] as Map) : root;
            if (root.containsKey('data') && root['data'] == null) return const Center(child: Text('No active record found.'));
            return ListView(
              padding: const EdgeInsets.all(16),
              children: data.entries.where((e) => e.value == null || e.value is! List).map((entry) {
                final value = entry.value is Map ? (entry.value as Map).entries.map((e) => '${e.key}: ${e.value ?? '—'}').join('\n') : '${entry.value ?? '—'}';
                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(_label(entry.key)),
                  subtitle: Text(value),
                );
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
        if (snapshot.hasError) return _ErrorView(message: snapshot.error.toString(), retry: () => setState(() => _future = widget.api.get('/seat-allocation')));
        final data = Map<String, dynamic>.from((snapshot.data?['data'] as Map?) ?? {});
        final active = data['active'] as Map?;
        final history = data['history'] as List? ?? const [];
        return ListView(padding: const EdgeInsets.all(16), children: [
          Text('Current Allocation', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          Card(child: Padding(padding: const EdgeInsets.all(16), child: Text(active == null ? 'No active seat allocation.' : _mapSummary(active)))),
          const SizedBox(height: 20),
          Text('History', style: Theme.of(context).textTheme.titleLarge),
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
        if (snapshot.hasError) return _ErrorView(message: snapshot.error.toString(), retry: () => setState(() => _future = widget.api.get('/qr-member-id')));
        final data = snapshot.data ?? {};
        return ListView(padding: const EdgeInsets.all(24), children: [
          const Icon(Icons.qr_code_2, size: 140),
          const SizedBox(height: 20),
          Text(data['name']?.toString() ?? 'Student', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
          Text(data['student_code']?.toString() ?? '—', textAlign: TextAlign.center),
          const SizedBox(height: 24),
          SelectableText('Member Token: ${data['qr_token'] ?? '—'}'),
          const SizedBox(height: 12),
          SelectableText('Attendance URL: ${data['attendance_url'] ?? '—'}'),
          const SizedBox(height: 12),
          const Text('A scannable QR graphic will be added during the release-design pass; this screen is already backed by the live QR API.'),
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
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Support / Enquiry')),
    body: ListView(padding: const EdgeInsets.all(16), children: [
      TextField(controller: _controller, maxLines: 7, maxLength: 2000, decoration: const InputDecoration(labelText: 'Message', border: OutlineInputBorder())),
      const SizedBox(height: 12),
      FilledButton.icon(onPressed: _sending ? null : _submit, icon: const Icon(Icons.send), label: Text(_sending ? 'Sending...' : 'Submit')),
    ]),
  );
}

String _label(String key) => key.replaceAll('_', ' ').split(' ').map((e) => e.isEmpty ? e : '${e[0].toUpperCase()}${e.substring(1)}').join(' ');
String _mapSummary(Map map) => map.entries.where((e) => e.value != null && e.value is! Map && e.value is! List).map((e) => '${_label(e.key.toString())}: ${e.value}').join('\n');

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.retry});
  final String message;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [Text(message, textAlign: TextAlign.center), const SizedBox(height: 12), FilledButton(onPressed: retry, child: const Text('Retry'))])));
}
