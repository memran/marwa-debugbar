<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Core;

final class DebugState
{
    public function __construct(
        public readonly float $requestStart,
        /** @var array<int,array{t:float,label:string}> */
        public readonly array $marks,
        /** @var array<int,array{time:float,level:string,message:string,context:array}> */
        public readonly array $logs,
        /** @var array<int,array{sql:string,params:array,duration_ms:float,connection:?string}> */
        public readonly array $queries,
        /** @var array<int,array{name:?string,file:?string,line:?int,html:string,time:float}> */
        public readonly array $dumps,
        /** @var array<int,array{
              type:string,message:string,code:int,file:string,line:int,
              time_ms:float, trace:string, chain:array<int,array{type:string,message:string,code:int,file:string,line:int,trace?:string}>
        }> */
        public readonly array $exceptions = []
    ) {}
}
