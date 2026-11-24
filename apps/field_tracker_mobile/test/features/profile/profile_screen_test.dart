import 'package:field_tracker_mobile/features/profile/profile_screen.dart';
import 'package:field_tracker_mobile/features/profile/user_profile.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

void main() {
  testWidgets('profile shows locked admin-managed fields', (tester) async {
    const profile = UserProfile(
      id: 1,
      name: 'Jane Doe',
      email: 'jane@example.com',
      phone: '01700000000',
      staffId: 'EMP-001',
      designation: 'Field Officer',
      photoUrl: null,
      dutyStartTime: '09:00:00',
      dutyEndTime: '17:00:00',
      totalTrackingSeconds: 3600,
    );

    await tester.pumpWidget(
      MaterialApp(
        home: ProfileScreen(
          profile: profile,
          onSaveProfile: ({required String email, required String? phone}) async =>
              profile,
          onUploadPhoto: () async => 'https://example.com/photo.jpg',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Name'), findsOneWidget);
    expect(find.text('Staff ID'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.text('Designation'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();

    expect(find.text('Duty time'), findsOneWidget);
    expect(find.text('Tracking time'), findsOneWidget);
    expect(find.text('Designation'), findsOneWidget);

    // Locked/admin-managed fields should not be editable text fields.
    expect(find.widgetWithText(TextFormField, 'Jane Doe'), findsNothing);
    expect(find.widgetWithText(TextFormField, 'EMP-001'), findsNothing);
    expect(find.widgetWithText(TextFormField, 'Field Officer'), findsNothing);
    expect(find.text('Jane Doe'), findsOneWidget);
    expect(find.text('EMP-001'), findsOneWidget);
    expect(find.text('Field Officer'), findsOneWidget);
  });
}
