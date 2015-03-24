# How to use
#### 1. Insert and Update
* HTTP Method: PUT
* URL Address:
* Insert: elastic/insert
* Update: elastic/update
* Input Body:
```
{
	"database": "iran",
	"table": "application",
	"key": "123",
	"fields": {
	"key": "value"
	}
}
```
#### 2. Select
* HTTP Method: GET
* URL Format: elastic/select/database/table/Key
#### 3. Delete Document
* HTTP Method: DELETE
* URL Format: elastic/deletedocument/database/table/Key
#### 4. Search
* HTTP Method: GET
* URL Format: elastic/search/database/table/offset/limit/?word
#### 5. Import
* HTTP Method: PUT
* URL Format: elastic/import
* Input Body:
```
[
	{
		"key1": "value1",
		"key2": "value2"
	},
	{
		"key1": "value1",
		"key2": "value2"
	}
]
```
# Go to zero state:
#### 1. Delete database
* http method : DELETE
* URL : elastic/deletedatabase/database
#### 2. Reset
* http method : GET
* URL : elastic/reset
#### 3. Check
* http method : GET
* URL : elastic/currentdb
