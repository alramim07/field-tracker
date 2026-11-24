import 'package:material_ui/material_ui.dart';

import '../profile/user_profile.dart';

class AppDrawer extends StatelessWidget {
  const AppDrawer({
    super.key,
    required this.profile,
    required this.onHome,
    required this.onCurrentLocation,
    required this.onTravelHistory,
    required this.onNotifications,
    required this.onUpdateProfile,
    required this.onChangePassword,
    required this.onLogout,
  });

  final UserProfile? profile;
  final VoidCallback onHome;
  final VoidCallback onCurrentLocation;
  final VoidCallback onTravelHistory;
  final VoidCallback onNotifications;
  final VoidCallback onUpdateProfile;
  final VoidCallback onChangePassword;
  final Future<void> Function() onLogout;

  @override
  Widget build(BuildContext context) {
    final p = profile;
    return Drawer(
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 12),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 32,
                    backgroundImage: (p?.photoUrl != null && p!.photoUrl!.isNotEmpty)
                        ? NetworkImage(p.photoUrl!)
                        : null,
                    child: (p?.photoUrl == null || p!.photoUrl!.isEmpty)
                        ? const Icon(Icons.person, size: 32)
                        : null,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          p?.name ?? 'Employee',
                          style: Theme.of(context).textTheme.titleMedium,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Staff ID: ${p?.staffId ?? '-'}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        Text(
                          'Phone: ${p?.phone ?? '-'}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        Text(
                          'Designation: ${p?.designation ?? '-'}',
                          style: Theme.of(context).textTheme.bodySmall,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                padding: EdgeInsets.zero,
                children: [
                  ListTile(
                    leading: const Icon(Icons.home_outlined),
                    title: const Text('Home'),
                    onTap: onHome,
                  ),
                  ListTile(
                    key: const Key('drawer_current_location'),
                    leading: const Icon(Icons.my_location_outlined),
                    title: const Text('Current location'),
                    onTap: onCurrentLocation,
                  ),
                  ListTile(
                    key: const Key('drawer_travel_history'),
                    leading: const Icon(Icons.route_outlined),
                    title: const Text('Travel history'),
                    onTap: onTravelHistory,
                  ),
                  ListTile(
                    leading: const Icon(Icons.notifications_outlined),
                    title: const Text('Notifications'),
                    onTap: onNotifications,
                  ),
                  ListTile(
                    key: const Key('drawer_update_profile'),
                    leading: const Icon(Icons.person_outline),
                    title: const Text('Update profile'),
                    onTap: onUpdateProfile,
                  ),
                  ListTile(
                    key: const Key('drawer_change_password'),
                    leading: const Icon(Icons.lock_outline),
                    title: const Text('Change PASSWORD'),
                    onTap: onChangePassword,
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            ListTile(
              key: const Key('drawer_logout'),
              leading: const Icon(Icons.logout),
              title: const Text('Logout'),
              onTap: () async {
                Navigator.of(context).maybePop();
                await onLogout();
              },
            ),
          ],
        ),
      ),
    );
  }
}
