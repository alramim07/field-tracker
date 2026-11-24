import 'package:material_ui/material_ui.dart';

import 'notification_item.dart';
import 'notification_store.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key, required this.store});

  final NotificationStore store;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  late Future<List<NotificationItem>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.store.list();
  }

  Future<void> _reload() async {
    setState(() {
      _future = widget.store.list();
    });
    await _future;
  }

  Future<void> _markRead(NotificationItem item) async {
    if (item.isRead) return;
    await widget.store.markRead(item.id);
    await _reload();
  }

  Future<void> _markAllRead() async {
    await widget.store.markAllRead();
    await _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: _markAllRead,
            child: const Text('Mark all read'),
          ),
        ],
      ),
      body: FutureBuilder<List<NotificationItem>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          final items = snapshot.data ?? const <NotificationItem>[];
          if (items.isEmpty) {
            return const Center(child: Text('No notifications'));
          }
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView.separated(
              itemCount: items.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final item = items[index];
                return ListTile(
                  key: Key('notification_${item.id}'),
                  leading: Icon(
                    item.isRead
                        ? Icons.notifications_none
                        : Icons.notifications_active,
                    color: item.isRead
                        ? Theme.of(context).colorScheme.outline
                        : Theme.of(context).colorScheme.primary,
                  ),
                  title: Text(item.title),
                  subtitle: Text(item.body),
                  trailing: item.isRead
                      ? null
                      : const Icon(Icons.circle, size: 10),
                  onTap: () => _markRead(item),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
