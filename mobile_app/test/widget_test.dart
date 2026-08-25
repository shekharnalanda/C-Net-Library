import 'package:flutter_test/flutter_test.dart';
import 'package:cnetlibrary/src/app.dart';

void main() {
  testWidgets('shows C-Net Library splash while session loads', (tester) async {
    await tester.pumpWidget(const CNetLibraryApp());
    expect(find.text('C-Net Library'), findsOneWidget);
    expect(find.text('Powered by MCI Educational Group'), findsOneWidget);
  });
}
