<?php

namespace Modules\ZabbixIm\Lib;

interface ImProviderInterface {

    /**
     * @return array<int, array{id:string,name:string,parent_id:string}>
     */
    public function getDepartments(): array;

    /**
     * @return array<int, array{id:string,name:string,username:string,email:string,mobile:string}>
     */
    public function getDepartmentUsers(string $departmentId): array;

    /**
     * Debug log of IM API calls in this request (including raw HTTP responses)
     *
     * @return array<int, array<string, mixed>>
     */
    public function getApiDebugLog(): array;
}
