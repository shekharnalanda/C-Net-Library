import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/api_client.dart';

class ReleaseQrMemberScreen extends StatefulWidget {
  const ReleaseQrMemberScreen({super.key, required this.api});
  final ApiClient api;

  @override
  State<ReleaseQrMemberScreen> createState() => _ReleaseQrMemberScreenState();
}

class _ReleaseQrMemberScreenState extends State<ReleaseQrMemberScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.api.get('/qr-member-id');
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('QR Member ID')),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: FilledButton(
                  onPressed: () => setState(() => _future = widget.api.get('/qr-member-id')),
                  child: const Text('Retry'),
                ),
              );
            }

            final data = snapshot.data ?? {};
            final attendanceUrl = data['attendance_url']?.toString() ?? '';
            return ListView(
              padding: const EdgeInsets.all(24),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      children: [
                        Text(data['name']?.toString() ?? 'Student', style: Theme.of(context).textTheme.headlineSmall, textAlign: TextAlign.center),
                        const SizedBox(height: 4),
                        Text(data['student_code']?.toString() ?? '—'),
                        const SizedBox(height: 24),
                        if (attendanceUrl.isNotEmpty)
                          QrImageView(data: attendanceUrl, size: 230, eyeStyle: const QrEyeStyle(eyeShape: QrEyeShape.square), dataModuleStyle: const QrDataModuleStyle(dataModuleShape: QrDataModuleShape.square))
                        else
                          const Icon(Icons.qr_code_2, size: 180),
                        const SizedBox(height: 16),
                        const Text('Scan at the C-Net Library attendance desk', textAlign: TextAlign.center),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                if (attendanceUrl.isNotEmpty)
                  OutlinedButton.icon(
                    onPressed: () async {
                      final uri = Uri.tryParse(attendanceUrl);
                      if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
                    },
                    icon: const Icon(Icons.open_in_new),
                    label: const Text('Open Attendance Link'),
                  ),
              ],
            );
          },
        ),
      );
}
