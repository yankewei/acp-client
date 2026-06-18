# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial CHANGELOG.
- Composer scripts: `test`, `analyze`, `lint`, `format`.
- `.gitattributes` to exclude development files from distribution archives.

### Changed

- Refactored `AgentRequestDispatcher` typed handlers into a single generic pipeline.
- Centralized prompt content-block type definitions in `ContentBlockType`.
