# Graph Report - basesistem  (2026-10-27)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 1113 nodes · 1947 edges · 93 communities (43 shown, 50 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 54 edges (avg confidence: 0.85)
- Token cost: 3,599 input · 895 output

## Graph Freshness
- Built from commit: `13144254`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Inertia Shared Middleware
- WhatsApp Webhook Processing
- Profile Image Cropping
- Frontend Dependencies
- Notification Broadcasting Events
- Notification Docs and Resolver
- Workspace Calendar Events
- Assistant Calendar Controller
- Notifications API
- Inertia UI Form Components
- Users Management UI
- Login Requests and Config
- Auth Session Controllers
- API Token Management UI
- User Model and Actions
- Auth Profile Controllers
- Audio Transcription Service
- Database Migrations
- Frontend Build Tooling
- Roles and Theme Routing
- WhatsApp Instance Management
- Profile and WhatsApp Controllers
- Breeze Base UI Components
- Scheduled Reminder Commands
- Dropdown and Theme Selector
- Realtime Notification Composable
- Auth Layout and Branding
- Wrapping Select Component
- Assistant Tool Call Tests
- Docker Compose Services
- Docker App Container
- Notification Dispatch Channels
- Auth Middleware
- MongoDB Container
- Redis Container
- Mail and Selenium Containers
- Password Reset Tests
- API Token Tests
- Composer Metadata
- PHP Dependencies
- PHP Dev Dependencies
- Composer Scripts
- Password and Registration Tests
- Account Deletion UI
- App Service Provider
- Email Verification Tests
- Modal Component
- Meilisearch Container
- Composer Config
- Roles Permissions UI
- Profile Feature Tests
- Password and Account State
- Sail Environment Variables
- Test Base Setup
- JS Path Aliases
- Authentication Tests
- Account State Screens
- Async Assistant Queue Test
- Sanctum Token Model
- App Bootstrap Config
- Port Mapping Config
- Autoload Mapping
- Password Confirmation Tests
- Permissions Access Tests
- New Password Screens
- Test Autoload
- Package Extra Config
- MariaDB Testing DB Script
- MySQL Testing DB Script

## God Nodes (most connected - your core abstractions)
1. `User` - 62 edges
2. `AssistantOrchestratorService` - 40 edges
3. `Controller` - 32 edges
4. `@inertiajs/vue3` - 32 edges
5. `TestCase` - 28 edges
6. `vue` - 27 edges
7. `EvolutionApiService` - 26 edges
8. `AssistantController` - 19 edges
9. `AssistantMessageProcessed` - 16 edges
10. `GoogleCalendarService` - 15 edges

## Surprising Connections (you probably didn't know these)
- `AssistantTaskClassificationAndAtomicityTest` --references--> `AssistantOrchestratorService`  [EXTRACTED]
  tests/Unit/AssistantTaskClassificationAndAtomicityTest.php → app/Services/Assistant/AssistantOrchestratorService.php
- `{closure#5}()` --calls--> `ObjectId`  [EXTRACTED]
  app/Http/Controllers/users.php → stubs/MongoDB/BSON/ObjectId.php
- `WhatsAppAntiBanPipelineTest` --references--> `WhatsAppAntiBanPipeline`  [EXTRACTED]
  tests/Unit/WhatsAppAntiBanPipelineTest.php → app/Services/Whatsapp/WhatsAppAntiBanPipeline.php
- `AudioTranscriptionServiceTest` --references--> `AssistantOrchestratorService`  [EXTRACTED]
  tests/Unit/AudioTranscriptionServiceTest.php → app/Services/Assistant/AssistantOrchestratorService.php
- `EvolutionWebhookController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Api/EvolutionWebhookController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Flujo de notificaciones (panel, API REST y tiempo real)** — resources_js_components_notificationbell, readme_notificationsmodel, readme_app_support_notificationlinkresolver, readme_notificaciontouser, resources_js_layouts_authenticatedlayout [EXTRACTED 0.90]

## Communities (93 total, 50 thin omitted)

### Community 0 - "Inertia Shared Middleware"
Cohesion: 0.06
Nodes (12): HandleInertiaRequests, CalendarEvent, ComponentThemeModel, LogsModel, ModulesModel, PermissionsModel, Reminder, RoleModel (+4 more)

### Community 1 - "WhatsApp Webhook Processing"
Cohesion: 0.07
Nodes (6): EvolutionWebhookController, WhatsAppWebhookController, EvolutionApiService, WhatsAppAntiBanPipeline, ExampleTest, WhatsAppAntiBanPipelineTest

### Community 2 - "Profile Image Cropping"
Cohesion: 0.06
Nodes (33): apply(), close(), emit, imgRef, previewClass, previewLabel, previewRef, props (+25 more)

### Community 3 - "Frontend Dependencies"
Cohesion: 0.08
Nodes (31): allowScripts, esbuild@0.28.2, dependencies, cropperjs@^1.6.2, daisyui@^5.0.0, laravel-echo@^2.3.1, primeicons@^7.0.0, @primeuix/themes@^2.0.3 (+23 more)

### Community 4 - "Notification Broadcasting Events"
Cohesion: 0.10
Nodes (4): AssistantMessageProcessed, NotificacionToUser, ProcessAssistantMessage, AssistantMessage

### Community 5 - "Notification Docs and Resolver"
Cohesion: 0.08
Nodes (30): Base Sistema README, App\Support\NotificationLinkResolver::resolve(), docs/NOTIFICACIONES-Y-BROADCASTING.md, docs/README.md (documentation index), HandleInertiaRequests (middleware), NotificacionToUser (evento broadcast), NotificationsModel (MongoDB), authHeaders() (+22 more)

### Community 6 - "Workspace Calendar Events"
Cohesion: 0.06
Nodes (26): calendarSettings, events, fetchFeed(), inputQuery, isConfigDialogOpen, isFeedLoading, isLoading, isNewEventDialogOpen (+18 more)

### Community 7 - "Assistant Calendar Controller"
Cohesion: 0.16
Nodes (3): AssistantController, GoogleCalendarSetting, GoogleCalendarService

### Community 8 - "Notifications API"
Cohesion: 0.10
Nodes (7): {closure#2}(), NotificationsController, {closure#5}(), users, NotificationsModel, NotificationLinkResolver, ObjectId

### Community 9 - "Inertia UI Form Components"
Cohesion: 0.11
Nodes (10): @inertiajs/vue3, input, model, form, form, form, form, form (+2 more)

### Community 10 - "Users Management UI"
Cohesion: 0.08
Nodes (26): closeCreateDialog(), closeDeleteDialog(), closeEditDialog(), createDialogVisible, deleteDialogVisible, dialItemsFor(), editDialogVisible, filterRoleId (+18 more)

### Community 11 - "Login Requests and Config"
Cohesion: 0.10
Nodes (3): LoginRequest, ProfileUpdateRequest, UserFactory

### Community 12 - "Auth Session Controllers"
Cohesion: 0.11
Nodes (7): AuthenticatedSessionController, ConfirmablePasswordController, EmailVerificationNotificationController, PasswordResetLinkController, RegisteredUserController, VerifyEmailController, Controller

### Community 13 - "API Token Management UI"
Cohesion: 0.09
Nodes (16): closeCreateDialog(), confirmRevokeAllDialogVisible, confirmRevokeDialogVisible, copiedCurl, copiedToken, createDialogVisible, exampleCurl, expirationOptions (+8 more)

### Community 18 - "Database Migrations"
Cohesion: 0.16
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 19 - "Frontend Build Tooling"
Cohesion: 0.11
Nodes (18): devDependencies, autoprefixer@^10.4.12, concurrently@^9.0.1, @inertiajs/vue3@^2.0.0, postcss@^8.4.31, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^7.0.7 (+10 more)

### Community 21 - "WhatsApp Instance Management"
Cohesion: 0.13
Nodes (14): autoRefreshInterval, createForm, currentQr, fetchQrCode(), handleCreate(), loadingQr, pairingCode, pollingInterval (+6 more)

### Community 23 - "Breeze Base UI Components"
Cohesion: 0.12
Nodes (11): vue, emit, props, proxyChecked, classes, props, classes, props (+3 more)

### Community 24 - "Scheduled Reminder Commands"
Cohesion: 0.21
Nodes (3): DispatchDueRemindersCommand, ProcessDueRemindersCommand, SendDailyDigestCommand

### Community 25 - "Dropdown and Theme Selector"
Cohesion: 0.15
Nodes (11): alignmentClasses, open, props, widthClass, applyTheme(), DARK_THEMES, displayedTheme, page (+3 more)

### Community 26 - "Realtime Notification Composable"
Cohesion: 0.18
Nodes (9): NOTIFICACION_TO_USER_EVENT, PROFILE_HIGHLIGHT_FIELDS_EVENT, broadcastLinks(), broadcastUrls(), can, menu, page, showingNavigationDropdown (+1 more)

### Community 27 - "Auth Layout and Branding"
Cohesion: 0.18
Nodes (9): attrs, logoUrl, page, resolveBrandingSrc(), authSideBackgroundStyle, authSideImageUrl, page, resolveBrandingSrc() (+1 more)

### Community 28 - "Wrapping Select Component"
Cohesion: 0.19
Nodes (11): attrs, attrsWithoutPt, baseWrapPt, mergedPt, mergePt(), mergeSection(), model, props (+3 more)

### Community 30 - "Docker Compose Services"
Cohesion: 0.18
Nodes (10): networks, sail, driver, driver, driver, driver, volumes, sail-meilisearch (+2 more)

### Community 31 - "Docker App Container"
Cohesion: 0.18
Nodes (11): WWWGROUP, args, context, dockerfile, build, depends_on, extra_hosts, image (+3 more)

### Community 34 - "MongoDB Container"
Cohesion: 0.20
Nodes (10): MONGO_INITDB_DATABASE, MONGO_INITDB_ROOT_PASSWORD, MONGO_INITDB_ROOT_USERNAME, environment, image, networks, ports, restart (+2 more)

### Community 35 - "Redis Container"
Cohesion: 0.24
Nodes (10): retries, test, timeout, healthcheck, healthcheck, image, networks, ports (+2 more)

### Community 36 - "Mail and Selenium Containers"
Cohesion: 0.20
Nodes (10): image, networks, ports, extra_hosts, image, networks, volumes, services (+2 more)

### Community 39 - "Composer Metadata"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 40 - "PHP Dependencies"
Cohesion: 0.22
Nodes (9): require, inertiajs/inertia-laravel, laravel/framework, laravel/reverb, laravel/sanctum, laravel/tinker, mongodb/laravel-mongodb, php (+1 more)

### Community 41 - "PHP Dev Dependencies"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 42 - "Composer Scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 44 - "Account Deletion UI"
Cohesion: 0.25
Nodes (5): closeModal(), confirmingUserDeletion, deleteUser(), form, passwordInput

### Community 47 - "Modal Component"
Cohesion: 0.32
Nodes (7): close(), closeOnEscape(), dialog, emit, maxWidthClass, props, showSlot

### Community 48 - "Meilisearch Container"
Cohesion: 0.29
Nodes (7): MEILI_NO_ANALYTICS, environment, image, networks, ports, volumes, meilisearch

### Community 49 - "Composer Config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 50 - "Roles Permissions UI"
Cohesion: 0.33
Nodes (4): emit, handleChange(), isChecked, props

### Community 53 - "Sail Environment Variables"
Cohesion: 0.33
Nodes (6): IGNITION_LOCAL_SITES_PATH, LARAVEL_SAIL, WWWUSER, XDEBUG_CONFIG, XDEBUG_MODE, environment

### Community 55 - "JS Path Aliases"
Cohesion: 0.33
Nodes (5): compilerOptions, baseUrl, paths, exclude, ziggy-js

### Community 61 - "Port Mapping Config"
Cohesion: 0.40
Nodes (5): ports, mode, protocol, published, target

### Community 62 - "Autoload Mapping"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 71 - "Test Autoload"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 72 - "Package Extra Config"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **2 isolated node(s):** `docs/NOTIFICACIONES-Y-BROADCASTING.md`, `docs/README.md (documentation index)`
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 500 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **50 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User Model and Actions` to `Inertia Shared Middleware`, `Notification Broadcasting Events`, `Assistant Calendar Controller`, `Notifications API`, `Login Requests and Config`, `Assistant Orchestration Core`, `Auth Profile Controllers`, `Audio Transcription Service`, `Scheduled Reminder Commands`, `Assistant Tool Call Tests`, `Notification Dispatch Channels`, `Auth Middleware`, `Password Reset Tests`, `API Token Tests`, `Password and Registration Tests`, `Email Verification Tests`, `Profile Feature Tests`, `Authentication Tests`, `Async Assistant Queue Test`, `Password Confirmation Tests`?**
  _High betweenness centrality (0.088) - this node is a cross-community bridge._
- **What connects `docs/NOTIFICACIONES-Y-BROADCASTING.md`, `docs/README.md (documentation index)` to the rest of the system?**
  _2 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Inertia Shared Middleware` be split into smaller, more focused modules?**
  _Cohesion score 0.05711263881544157 - nodes in this community are weakly interconnected._
- **Why does `vue` connect `Breeze Base UI Components` to `Profile Image Cropping`, `Frontend Dependencies`, `Notification Docs and Resolver`, `Workspace Calendar Events`, `Inertia UI Form Components`, `Users Management UI`, `Account Deletion UI`, `API Token Management UI`, `Modal Component`, `Roles Permissions UI`, `Frontend Build Tooling`, `WhatsApp Instance Management`, `Dropdown and Theme Selector`, `Realtime Notification Composable`, `Auth Layout and Branding`, `Wrapping Select Component`?**
  _High betweenness centrality (0.044) - this node is a cross-community bridge._
- **Should `WhatsApp Webhook Processing` be split into smaller, more focused modules?**
  _Cohesion score 0.06704260651629072 - nodes in this community are weakly interconnected._
- **Why does `EvolutionApiService` connect `WhatsApp Webhook Processing` to `Async Assistant Queue Test`, `Notification Broadcasting Events`, `Profile and WhatsApp Controllers`, `Assistant Orchestration Core`?**
  _High betweenness centrality (0.039) - this node is a cross-community bridge._
- **Should `Profile Image Cropping` be split into smaller, more focused modules?**
  _Cohesion score 0.05641025641025641 - nodes in this community are weakly interconnected._