

[![Build Status](https://travis-ci.org/fxrm/fxrm-store.png?branch=master)](https://travis-ci.org/fxrm/fxrm-store)

Fxrm Store
==========

A minimalist data access layer (non-traditional ORM) focusing on transparency, [domain-driven design](http://www.infoq.com/presentations/model-to-work-evans) and model testability. Used in production for over two years.

Built around the notions of [business primitives](http://codebetter.com/drusellers/2010/01/27/business-primitives-1-2/) and **lean identities**. The library does not require injecting any custom parent object classes or special annotation tags into application model code. Actual database implementation code is introduced to the business logic via inversion-of-control, so the business logic is left pristine and fully unit-testable without having Fxrm Store as a test dependency.

## Mental Model

The philosophy behind the Fxrm Store persistence model is not the same as traditional ORM layers, and drives a lot of the resulting implementation assumptions. This takes as granted that application code comes first, and that database storage schema responds to it, as a separate "downstream" persistence concern.

Relational data models are very apt for modeling common business concepts. For example, name and mailing address are not a person's *intrinsic* state, but rather an assigned extrinsic set of attributes: so it is very natural to represent that person as a unique immutable identifier and correlate the associated data as extrinsic values attached to that identifier. Data exists in relation to the identity: neighbouring columns in the same table row.

Fxrm Store library embraces the philosophy of the relational model deeper than a typical ORM: instead of representing relational columns as simple object fields in an entity class, they are coded as external properties of an *identity* class.

What that means is that the data access layer interfaces contain simple getters and setters for properties that always require an entity identifier to access the corresponding value. E.g. `function setUserName(UserId $id, $name)` sets the value of the `name` column in the `User` table, provided that row's `$id`. The converse `function getUserName(UserId $id)` retrieves the value of the same column. There is a corresponding simple `find` notation, and that's the entirety of the Fxrm data definition language.

An even "purer" approach, for comparison, could be modeling each attribute as a "hash-map" with entity identifiers as keys. That is close to how column-based or key-value stores work. However, we still want to use mainstream relational (SQL) or document-based storage, and we want to retain the familiar `get`/`set` imperative syntax.

## Working With Database Storage

Application developers describe the application's data access layer as one or several `abstract class` or `interface` definitions. Each definition contains simple `getXYZ`, `setXYZ` or `findXYZ` method signatures, tagged with return types where needed. No interface is tied to any one specific database table - developers are encouraged to mix and match entities and fields being accessed, grouping *by topic* rather than by identity.

The application's bootstrap code loads the Fxrm Store library, configures database driver parameters and then requests the library to "implement" the defined interfaces as needed. The library scans the method definitions and fills out the declared method signatures with actual backing database queries, providing the implementation as an object inheriting the original requested `interface` or `abstract class`. Then, that implementation can be injected into the business logic and normal operation proceeeds.

This approach allows unit-test harnesses to completely avoid loading the Fxrm Store library: any standard unit testing library can easily mock the data layer implementation, since the interface consists only of simple immediate getters/setters.

The data layer calling convention uses an imperative call style. Actual Fxrm Store implementations do not cache any of the data in memory, unlike typical ORM entity classes. There is no need for data dirty-checks, and reads are either simple logical values or complex batched datasets. All the storage driver calls are immediate: instead of dealing with complexities of a traditional ORM change-set the developers are instead directed to rely on native database transactions.

Optimization is not a concern for the database updates, and read optimization is best kept out of model code. Command/query responsibility separation (CQRS) principles show that optimized and batched reading belongs in a separate code silo anyway. This library allows to skip to "native" query level to help such read optimization.

## Lean Identities

SQL tables are represented by identity classes. Unlike entity classes (that map a `get`/`set` pair to each table field), identity classes are empty. A table named `User` is reflected as an empty class (with no parent) named `UserId`. This reflects the **lean identity** concept.

Lean identity class is empty because the actual identity object reference is itself the unique identifier of the underlying table row or document. It is immutable, so it has no state (class properties) to modify. Because object references are neither strings nor integers, the ORM layer transparently serializes/deserializes the database representation into application memory by keeping a map of references to underlying storable value. It also guarantees referential equality when the same identifier is retrieved via multiple queries.

This keeps business logic unconcerned with how unique identifiers are stored, and free of temptation to do math/string operations on those identifiers.
